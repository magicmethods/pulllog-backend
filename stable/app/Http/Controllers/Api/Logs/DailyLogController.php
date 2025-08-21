<?php

namespace App\Http\Controllers\Api\Logs;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\Log;
use App\Models\StatsCache;
use App\Services\LocaleResolver;
use App\Services\Demo\DemoLogProvider;
use App\Support\Responses\LogResponse;
use App\Support\Cache\StatsCachePurger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DailyLogController extends Controller
{
    use StatsCachePurger;

    /**
     * GET /logs/daily/{app}/{date}
     * 指定アプリの日別ログを取得
     */
    public function show(Request $request, string $appKey, string $date): JsonResponse|Response
    {
        $user = $request->user();
        $lang = LocaleResolver::resolve($request, $user);

        // アプリ解決
        $app = App::where('app_key', $appKey)->first();
        if (!$app) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.app_not_found', [], $lang),
            ], 404);
        }

        // 所有確認
        if (!$user->apps()->where('apps.id', $app->id)->exists()) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.permission_denied', [], $lang),
            ], 403);
        }

        // 日付検証
        try {
            $dateObj = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable $e) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.invalid_date_format', [], $lang),
            ], 400);
        }

        // デモユーザーなら合成データを返す（DBアクセスしない）
        if ($this->isDemoUser($user)) {
            /** @var \App\Models\App $app */
            $provider = app(DemoLogProvider::class);
            $d = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();

            // 単日の合成
            $rows = $provider->generateRange((int)$app->id, $d, $d);
            $row = $rows->first();

            if (!$row) {
                // デモでも「存在しない」は null 互換で返す
                return response('null', 200, ['Content-Type' => 'application/json']);
            }

            // 未保存の Log モデルに詰め替え
            $log = new Log([
                'user_id'         => $user->id,
                'app_id'          => $app->id,
                'log_date'        => $row['date'],
                'total_pulls'     => (int) ($row['total_pulls'] ?? 0),
                'discharge_items' => (int) ($row['discharge_items'] ?? 0),
                'expense_amount'  => (int) ($row['expense_amount'] ?? 0),
                'drop_details'    => $row['drop_details'] ?? null,
                'tags'            => $row['tags'] ?? null,
                'free_text'       => $row['free_text'] ?? null,
                'images'          => $row['images'] ?? null,
                'tasks'           => $row['tasks'] ?? null,
            ]);
            // $log->exists = false; // （既定で false。明示したいなら付けてもOK）

            return response()->json(LogResponse::toArray($log, $app), 200);
        }

        // ログ取得
        $log = Log::where('user_id', $user->id)
            ->where('app_id', $app->id)
            ->where('log_date', $dateObj->toDateString())
            ->first();

        if (!$log) {
            // 互換：存在しない場合は 'null'
            return response('null', 200, ['Content-Type' => 'application/json']);
        }

        return response()->json(LogResponse::toArray($log, $app), 200);
    }

    /**
     * POST /logs/daily/{app}/{date}
     * 指定アプリの日別ログを新規登録
     */
    public function insert(Request $request, string $appKey, string $date): JsonResponse|Response
    {
        $user = $request->user();
        $lang = LocaleResolver::resolve($request, $user);

        $app = App::where('app_key', $appKey)->first();
        if (!$app) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.app_not_found', [], $lang),
            ], 404);
        }

        if (!$user->apps()->where('apps.id', $app->id)->exists()) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.permission_denied', [], $lang),
            ], 403);
        }

        try {
            $dateObj = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable $e) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.invalid_date_format', [], $lang),
            ], 400);
        }

        $plan = $user->plan;
        if (!$plan) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.plan_not_found', [], $lang),
            ], 400);
        }

        // 互換のため expense / expense_amount の両方許容
        $validated = $request->validate([
            'total_pulls'      => 'required|integer|min:0',
            'discharge_items'  => 'required|integer|min:0',
            'expense'          => 'nullable|integer|min:0',
            'expense_amount'   => 'nullable|integer|min:0',
            'drop_details'     => 'nullable|array',
            'tags'             => ['nullable', 'array', 'max:' . $plan->max_log_tags],
            'tags.*'           => ['string', 'max:' . $plan->max_log_tag_length],
            'free_text'        => ['nullable', 'string', 'max:' . $plan->max_log_text_length],
            'images'           => 'nullable|array',
            'tasks'            => 'nullable|array',
        ]);

        $expenseAmount = $request->has('expense_amount')
            ? (int) $request->input('expense_amount')
            : (int) ($request->input('expense', 0));

        $logDate = $dateObj->toDateString();

        /** @var Log $log */
        $log = DB::transaction(function () use ($user, $app, $logDate, $validated, $expenseAmount) {
            return Log::create([
                'user_id'         => $user->id,
                'app_id'          => $app->id,
                'log_date'        => $logDate,
                'total_pulls'     => (int) $validated['total_pulls'],
                'discharge_items' => (int) $validated['discharge_items'],
                'expense_amount'  => $expenseAmount,
                'drop_details'    => $validated['drop_details'] ?? null,
                'tags'            => $validated['tags'] ?? null,
                'free_text'       => $validated['free_text'] ?? null,
                'images'          => $validated['images'] ?? null,
                'tasks'           => $validated['tasks'] ?? null,
            ]);
        });

        // キャッシュパージ
        $this->purgeStatsCacheForApp($user->id, $app->id, $appKey);

        return response()->json(LogResponse::toArray($log, $app), 201);
    }

    /**
     * PUT /logs/daily/{app}/{date}
     * 指定アプリの日別ログを更新（なければ作成）
     */
    public function update(Request $request, string $appKey, string $date): JsonResponse|Response
    {
        $user = $request->user();
        $lang = LocaleResolver::resolve($request, $user);

        $app = App::where('app_key', $appKey)->first();
        if (!$app) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.app_not_found', [], $lang),
            ], 404);
        }

        if (!$user->apps()->where('apps.id', $app->id)->exists()) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.permission_denied', [], $lang),
            ], 403);
        }

        try {
            $dateObj = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable $e) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.invalid_date_format', [], $lang),
            ], 400);
        }

        $plan = $user->plan;
        if (!$plan) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.plan_not_found', [], $lang),
            ], 400);
        }

        $validated = $request->validate([
            'total_pulls'      => 'required|integer|min:0',
            'discharge_items'  => 'required|integer|min:0',
            'expense'          => 'nullable|integer|min:0',
            'expense_amount'   => 'nullable|integer|min:0',
            'drop_details'     => 'nullable|array',
            'tags'             => ['nullable', 'array', 'max:' . $plan->max_log_tags],
            'tags.*'           => ['string', 'max:' . $plan->max_log_tag_length],
            'free_text'        => ['nullable', 'string', 'max:' . $plan->max_log_text_length],
            'images'           => 'nullable|array',
            'tasks'            => 'nullable|array',
        ]);

        $expenseAmount = $request->has('expense_amount')
            ? (int) $request->input('expense_amount')
            : (int) ($request->input('expense', 0));

        $logDate = $dateObj->toDateString();

        /** @var Log $log */
        $log = DB::transaction(function () use ($user, $app, $logDate, $validated, $expenseAmount) {
            $log = Log::where('user_id', $user->id)
                ->where('app_id', $app->id)
                ->where('log_date', $logDate)
                ->first();

            $attrs = [
                'user_id'         => $user->id,
                'app_id'          => $app->id,
                'log_date'        => $logDate,
                'total_pulls'     => (int) $validated['total_pulls'],
                'discharge_items' => (int) $validated['discharge_items'],
                'expense_amount'  => $expenseAmount,
                'drop_details'    => $validated['drop_details'] ?? null,
                'tags'            => $validated['tags'] ?? null,
                'free_text'       => $validated['free_text'] ?? null,
                'images'          => $validated['images'] ?? null,
                'tasks'           => $validated['tasks'] ?? null,
            ];

            if ($log) {
                $log->fill($attrs)->save();
            } else {
                $log = Log::create($attrs);
            }
            return $log;
        });

        // キャッシュパージ
        $this->purgeStatsCacheForApp($user->id, $app->id, $appKey);

        return response()->json(LogResponse::toArray($log, $app), 200);
    }

    /**
     * DELETE /logs/daily/{app}/{date}
     * 指定アプリの日別ログを削除
     */
    public function destroy(Request $request, string $appKey, string $date): JsonResponse
    {
        $user = $request->user();
        $lang = LocaleResolver::resolve($request, $user);

        $app = App::where('app_key', $appKey)->first();
        if (!$app) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.app_not_found', [], $lang),
            ], 404);
        }

        if (!$user->apps()->where('apps.id', $app->id)->exists()) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.permission_denied', [], $lang),
            ], 403);
        }

        try {
            $dateObj = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable $e) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.invalid_date_format', [], $lang),
            ], 400);
        }

        $log = Log::where('user_id', $user->id)
            ->where('app_id', $app->id)
            ->where('log_date', $dateObj->toDateString())
            ->first();

        if (!$log) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.log_not_found', [], $lang),
            ], 404);
        }

        $log->delete();
        // キャッシュパージ
        $this->purgeStatsCacheForApp($user->id, $app->id, $appKey);

        return response()->json([
            'state'   => 'success',
            'message' => trans('messages.log_deleted', [], $lang),
            'log'     => [
                'appId' => $appKey,
                'date'  => $dateObj->toDateString(),
            ],
        ], 200);
    }

    // デモ判定のヘルパ
    private function isDemoUser($user): bool
    {
        $demoEmail = (string) config('demo.demo_email', env('DEMO_EMAIL'));
        if ($demoEmail !== '' && strtolower($user->email ?? '') === strtolower($demoEmail)) {
            return true;
        }
        $demoUserIds = (array) config('demo.demo_user_ids', []);
        if (!empty($demoUserIds) && in_array((int)$user->id, array_map('intval', $demoUserIds), true)) {
            return true;
        }
        // 役割ベースがあるなら最後に
        if (property_exists($user, 'roles') && in_array('demo', $user->roles, true)) {
            return true;
        }
        return false;
    }

}
