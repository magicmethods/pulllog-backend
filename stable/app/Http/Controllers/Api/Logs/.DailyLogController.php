<?php

namespace App\Http\Controllers\Api\Logs;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\UserApp;
//use App\Models\Plan;
use App\Services\LocaleResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
//use Illuminate\Support\Facades\Validator;
//use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailyLogController extends Controller
{
    /**
     * GET /logs/daily/{app}/{date}
     * 指定アプリの日別ログを取得
     */
    public function show(Request $request, string $appKey, string $date): JsonResponse|Response
    {
        $user = $request->user();
        $userId = $user->id;
        $lang = LocaleResolver::resolve($request, $user);

        // app_keyでアプリ取得
        $app = App::where('app_key', $appKey)->first();
        if (!$app) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.app_not_found', [], $lang),
            ], 404);
        }

        // ユーザーがこのアプリに紐付いているかチェック
        $userApp = UserApp::where('user_id', $userId)
            ->where('app_id', $app->id)
            ->first();
        if (!$userApp) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.permission_denied', [], $lang),
            ], 403);
        }

        // 日付のフォーマットチェック
        try {
            $dateObj = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Exception $e) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.invalid_date_format', [], $lang),
            ], 400);
        }

        // 該当ログ検索
        $log = \App\Models\Log::where('user_id', $userId)
            ->where('app_id', $app->id)
            ->whereDate('log_date', $dateObj)
            ->first();

        // レスポンスデータ成形
        if ($log) {
            $response = $log->toArray();
            // appIdの値をappKeyに置き換え
            $response['appId'] = $appKey;
            unset($response['app_id']); // 内部IDは返却しない
            // log_dateのフォーマット統一
            $response['date'] = Carbon::parse($log->log_date)->format('Y-m-d');
            unset($response['log_date']);
        } else {
            return response('null', 200, ['Content-Type' => 'application/json']);
        }

        return response()->json($response);
    }
    /**
     * POST /logs/daily/{app}/{date}
     * 指定アプリの日別ログを新規登録
     */
    public function insert(Request $request, string $appKey, string $date): JsonResponse|Response
    {
        $user = $request->user();
        $userId = $user->id;
        $plan = $user->plan;
        $lang = LocaleResolver::resolve($request, $user);

        // app_keyでアプリ取得
        $app = App::where('app_key', $appKey)->first();
        if (!$app) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.app_not_found', [], $lang),
            ], 404);
        }

        // ユーザーがこのアプリに紐付いているかチェック
        $userApp = UserApp::where('user_id', $userId)
            ->where('app_id', $app->id)
            ->first();
        if (!$userApp) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.permission_denied', [], $lang),
            ], 403);
        }

        /*
        Log::debug('DailyLogController@insert', [
            'userId' => $userId,
            'appKey' => $appKey,
            'date' => $date,
            'app' => $app->toArray(),
            'userApp' => $userApp->toArray(),
        ]);
        */

        // 日付のフォーマットチェック
        try {
            $dateObj = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Exception $e) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.invalid_date_format', [], $lang),
            ], 400);
        }

        if (!$plan) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.plan_not_found', [], $lang),
            ], 400);
        }
        // 入力値バリデーション
        $validated = $request->validate([
            'total_pulls'      => 'required|integer|min:0',
            'discharge_items'  => 'required|integer|min:0',
            'expense'          => 'nullable|integer|min:0',
            'drop_details'     => 'nullable|array',
            'tags'             => ['nullable', 'array', 'max:' . $plan->max_log_tags],
            'tags.*'           => ['string', 'max:' . $plan->max_log_tag_length],
            'free_text'        => ['nullable', 'string', 'max:' . $plan->max_log_text_length],
            'images'           => 'nullable|array',
            'tasks'            => 'nullable|array',
        ]);

        // トランザクションでinsert
        DB::beginTransaction();
        try {
            $logAttrs = [
                'user_id' => $userId,
                'app_id'  => $app->id,
                'log_date' => $dateObj->copy(),
            ];
            $logAttrs = array_merge($logAttrs, $validated);

            /*
            Log::debug('DailyLogController@insert', [
                'logAttrs' => $logAttrs,
            ]);
            */
            // 新規登録
            $log = \App\Models\Log::create($logAttrs);
            // キャッシュ削除
            $this->deleteStatsCache($userId, $appKey);

            DB::commit();

            // レスポンス形式調整
            $response = $log->toArray();
            $response['appId'] = $appKey;
            $response['date'] = $dateObj->format('Y-m-d');
            unset($response['app_id'], $response['log_date']);

            return response()->json($response, 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            /*
            Log::error('DailyLogController@insertError', [
                'error' => $e->getMessage(),
                'userId' => $userId,
                'appKey' => $appKey,
                'date' => $date,
            ]);
            */
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.log_creation_failed', [], $lang),
            ], 500);
        }
    }
    /**
     * PUT /logs/daily/{app}/{date}
     * 指定アプリの日別ログを更新
     */
    public function update(Request $request, string $appKey, string $date): JsonResponse|Response
    {
        $user = $request->user();
        $userId = $user->id;
        $plan = $user->plan;
        $lang = LocaleResolver::resolve($request, $user);

        // app_keyでアプリ取得
        $app = App::where('app_key', $appKey)->first();
        if (!$app) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.app_not_found', [], $lang),
            ], 404);
        }

        // ユーザーがこのアプリに紐付いているかチェック
        $userApp = UserApp::where('user_id', $userId)
            ->where('app_id', $app->id)
            ->first();
        if (!$userApp) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.permission_denied', [], $lang),
            ], 403);
        }

        /*
        Log::debug('DailyLogController@update', [
            'userId' => $userId,
            'appKey' => $appKey,
            'date' => $date,
            'app' => $app->toArray(),
            'userApp' => $userApp->toArray(),
        ]);
        */
        // 日付のフォーマットチェック
        try {
            $dateObj = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Exception $e) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.invalid_date_format', [], $lang),
            ], 400);
        }

        if (!$plan) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.plan_not_found', [], $lang),
            ], 400);
        }
        // 入力値バリデーション
        $validated = $request->validate([
            'total_pulls'      => 'required|integer|min:0',
            'discharge_items'  => 'required|integer|min:0',
            'expense'          => 'nullable|integer|min:0',
            'drop_details'     => 'nullable|array',
            'tags'             => ['nullable', 'array', 'max:' . $plan->max_log_tags],
            'tags.*'           => ['string', 'max:' . $plan->max_log_tag_length],
            'free_text'        => ['nullable', 'string', 'max:' . $plan->max_log_text_length],
            'images'           => 'nullable|array',
            'tasks'            => 'nullable|array',
        ]);

        // トランザクションでinsert
        DB::beginTransaction();
        try {
            $log = \App\Models\Log::where('user_id', $userId)
                ->where('app_id', $app->id)
                ->whereDate('log_date', $dateObj)
                ->first();

            $logAttrs = [
                'user_id' => $userId,
                'app_id'  => $app->id,
                'log_date' => $dateObj->copy(),
            ];
            $logAttrs = array_merge($logAttrs, $validated);

            /*
            Log::debug('DailyLogController@update', [
                'logAttrs' => $logAttrs,
            ]);
            */
            if ($log) {
                // 既存更新
                $log->fill($logAttrs);
                $log->save();
            } else {
                // 既存ログがない場合は新規登録
                $log = \App\Models\Log::create($logAttrs);
            }
            // キャッシュ削除
            $this->deleteStatsCache($userId, $appKey);

            DB::commit();

            // レスポンス形式調整
            $response = $log->toArray();
            $response['appId'] = $appKey;
            $response['date'] = $dateObj->format('Y-m-d');
            unset($response['app_id'], $response['log_date']);

            return response()->json($response, 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            /*
            Log::error('DailyLogController@updateError', [
                'error' => $e->getMessage(),
                'userId' => $userId,
                'appKey' => $appKey,
                'date' => $date,
            ]);
            */
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.log_update_failed', [], $lang),
            ], 500);
        }
    }
    /**
     * DELETE /logs/daily/{app}/{date}
     * 指定アプリの日別ログを削除
     */
    public function destroy(Request $request, string $appKey, string $date): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;
        $lang = LocaleResolver::resolve($request, $user);

        // app_keyでアプリ取得
        $app = App::where('app_key', $appKey)->first();
        if (!$app) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.app_not_found', [], $lang),
            ], 404);
        }

        // ユーザーがこのアプリに紐付いているかチェック
        $userApp = UserApp::where('user_id', $userId)
            ->where('app_id', $app->id)
            ->first();
        if (!$userApp) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.permission_denied', [], $lang),
            ], 403);
        }

        // 日付のフォーマットチェック
        try {
            $dateObj = \Carbon\Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Exception $e) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.invalid_date_format', [], $lang),
            ], 400);
        }

        // 対象レコードを取得
        $log = \App\Models\Log::where('user_id', $userId)
            ->where('app_id', $app->id)
            ->whereDate('log_date', $dateObj)
            ->first();

        if (!$log) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.log_not_found', [], $lang),
            ], 404);
        }

        // 削除処理
        $log->delete();
        // キャッシュ削除
        $this->deleteStatsCache($userId, $appKey);

        return response()->json([
            'state' => 'success',
            'message' => trans('messages.log_deleted', [], $lang),
            'log' => [
                'appId' => $appKey,
                'date' => $date,
            ]
        ]);
    }
    /**
     * 対象キャッシュを一括削除
     */
    private function deleteStatsCache($userId, $appKey)
    {
        $pattern = "stats:{$userId}:{$appKey}:%";
        \App\Models\StatsCache::where('cache_key', 'like', $pattern)->delete();
    }
    

}
