<?php

namespace App\Http\Controllers\Api\Apps;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\StatsCache;
use App\Services\LocaleResolver;
use App\Support\Cache\StatsCachePurger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AppsController extends Controller
{
    use StatsCachePurger;

    /**
     * GET /apps
     * 認証ユーザーの登録アプリ一覧を取得
     */
    public function getAppList(Request $request): JsonResponse
    {
        $user = $request->user();
        $lang = LocaleResolver::resolve($request, $user);

        if (!$user) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('auth.unauthorized', [], $lang),
                'apps'    => [],
            ], 401);
        }

        // N+1回避のため currency を事前ロード
        $apps = $user->apps()
            ->with('currency')
            ->orderBy('created_at', 'desc')
            ->get();

        $appsList = $apps->map(fn (App $app) => $this->toResponseArray($app))->values();

        return response()->json($appsList, 200);
    }

    /**
     * POST /apps
     * 認証ユーザーの新規アプリ登録
     */
    public function registerApp(Request $request): JsonResponse
    {
        $user = $request->user();
        $lang = LocaleResolver::resolve($request, $user);

        if (!$user) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('auth.unauthorized', [], $lang),
            ], 401);
        }

        // バリデーション（currency_code は必須 & currencies.code に存在）
        $v = Validator::make($request->all(), [
            'name'             => 'required|string|max:128',
            'url'              => 'nullable|url|max:255',
            'description'      => 'nullable|string|max:400',
            'date_update_time' => ['required','string','size:5','regex:/^\d{2}:\d{2}$/'],
            'sync_update_time' => 'boolean',
            'currency_code'    => 'required|string|size:3|exists:currencies,code',
            'pity_system'      => 'boolean',
            'guarantee_count'  => 'integer|min:0',
            'rarity_defs'      => 'array',
            'marker_defs'      => 'array',
            'task_defs'        => 'array',
        ]);

        if ($v->fails()) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('auth.validation_failed', ['error' => $v->errors()->first()], $lang),
            ], 400);
        }

        $appKey = (string) Str::ulid();

        $app = DB::transaction(function () use ($request, $user, $appKey) {
            /** @var App $app */
            $app = App::create([
                'app_key'          => $appKey,
                'name'             => $request->string('name'),
                'url'              => $request->input('url'),
                'description'      => $request->input('description'),
                'date_update_time' => $request->string('date_update_time'),
                'sync_update_time' => (bool) $request->input('sync_update_time', false),
                'currency_code'    => strtoupper($request->string('currency_code')),
                'pity_system'      => (bool) $request->input('pity_system', false),
                'guarantee_count'  => (int) $request->input('guarantee_count', 0),
                'rarity_defs'      => $request->input('rarity_defs', []),
                'marker_defs'      => $request->input('marker_defs', []),
                'task_defs'        => $request->input('task_defs', []),
            ]);

            // 中間テーブルに紐付け
            $user->apps()->attach($app->id, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $app->load('currency');
        });

        return response()->json($this->toResponseArray($app), 201);
    }

    /**
     * GET /apps/{appId}
     * 認証ユーザーの指定アプリ情報を取得（app_key）
     */
    public function getAppData(Request $request, string $appId): JsonResponse
    {
        $user = $request->user();
        $lang = LocaleResolver::resolve($request, $user);

        if (!$user) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('auth.unauthorized', [], $lang),
            ], 401);
        }

        $app = $user->apps()
            ->with('currency')
            ->where('app_key', $appId)
            ->first();

        if (!$app) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('messages.app_not_found', [], $lang),
            ], 404);
        }

        return response()->json($this->toResponseArray($app), 200);
    }

    /**
     * PUT /apps/{appId}
     * 認証ユーザーの指定アプリ情報を更新（app_key）
     */
    public function updateApp(Request $request, string $appId): JsonResponse
    {
        $user = $request->user();
        $lang = LocaleResolver::resolve($request, $user);

        if (!$user) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('auth.unauthorized', [], $lang),
            ], 401);
        }

        /** @var App|null $app */
        $app = $user->apps()->where('app_key', $appId)->first();
        if (!$app) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('messages.app_not_found', [], $lang),
            ], 404);
        }

        $v = Validator::make($request->all(), [
            'name'             => 'required|string|max:128',
            'url'              => 'nullable|url|max:255',
            'description'      => 'nullable|string|max:400',
            'date_update_time' => ['required','string','size:5','regex:/^\d{2}:\d{2}$/'],
            'sync_update_time' => 'boolean',
            'currency_code'    => 'required|string|size:3|exists:currencies,code',
            'pity_system'      => 'boolean',
            'guarantee_count'  => 'integer|min:0',
            'rarity_defs'      => 'array',
            'marker_defs'      => 'array',
            'task_defs'        => 'array',
        ]);

        if ($v->fails()) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('auth.validation_failed', ['error' => $v->errors()->first()], $lang),
            ], 400);
        }

        $oldCurrency = $app->currency_code;

        $app = DB::transaction(function () use ($request, $app, $user, $oldCurrency) {
            $app->fill([
                'name'             => $request->string('name'),
                'url'              => $request->input('url'),
                'description'      => $request->input('description'),
                'date_update_time' => $request->string('date_update_time'),
                'sync_update_time' => (bool) $request->input('sync_update_time', false),
                'currency_code'    => strtoupper($request->string('currency_code')),
                'pity_system'      => (bool) $request->input('pity_system', false),
                'guarantee_count'  => (int) $request->input('guarantee_count', 0),
                'rarity_defs'      => $request->input('rarity_defs', []),
                'marker_defs'      => $request->input('marker_defs', []),
                'task_defs'        => $request->input('task_defs', []),
            ]);
            $app->save();

            // 通貨が変わった場合に「旧通貨サフィックス付きのキー」も掃除
            if ($oldCurrency !== $app->currency_code) {
                $this->purgeStatsCacheForApp($user->id, $app->id, $app->app_key, $oldCurrency);
            }
            // 現行通貨分も全レンジで掃除（新キー）
            $this->purgeStatsCacheForApp($user->id, $app->id, $app->app_key);

            return $app->load('currency');
        });

        return response()->json($this->toResponseArray($app), 200);
    }

    /**
     * DELETE /apps/{appId}
     * 認証ユーザーの指定アプリを物理削除（app_key）
     */
    public function deleteApp(Request $request, string $appId): JsonResponse
    {
        $user = $request->user();
        $lang = LocaleResolver::resolve($request, $user);

        if (!$user) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('auth.unauthorized', [], $lang),
            ], 401);
        }

        /** @var App|null $app */
        $app = $user->apps()->where('app_key', $appId)->first();
        if (!$app) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('messages.app_not_found', [], $lang),
            ], 404);
        }

        DB::transaction(function () use ($user, $app) {
            // ピボット解除
            $user->apps()->detach($app->id);

            // キャッシュパージ
            $this->purgeStatsCacheForApp($user->id, $app->id, $app->app_key, $app->currency_code);

            // ログ削除（ユーザー配下・当該アプリ）
            \App\Models\Log::where('user_id', $user->id)
                ->where('app_id', $app->id)
                ->delete();

            // アプリ本体削除
            $app->delete();
        });

        return response()->json(['state' => 'success'], 200);
    }

    // ========================
    // Helpers
    // ========================

    /** App -> APIレスポンス配列（フロント互換 + currency_code へ統一） */
    private function toResponseArray(App $app): array
    {
        return [
            'appId'            => $app->app_key,
            'name'             => $app->name,
            'url'              => $app->url,
            'description'      => $app->description,
            'date_update_time' => $app->date_update_time,
            'sync_update_time' => (bool) $app->sync_update_time,
            'currency_code'    => $app->currency_code,
            'pity_system'      => (bool) $app->pity_system,
            'guarantee_count'  => (int) $app->guarantee_count,
            'rarity_defs'      => $app->rarity_defs ?? [],
            'marker_defs'      => $app->marker_defs ?? [],
            'task_defs'        => $app->task_defs ?? [],
            'created_at'       => optional($app->created_at)->toIso8601String(),
            'updated_at'       => optional($app->updated_at)->toIso8601String(),
        ];
    }

}
