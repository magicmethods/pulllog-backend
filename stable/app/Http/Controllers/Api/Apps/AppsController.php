<?php

namespace App\Http\Controllers\Api\Apps;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\User;
use App\Models\AuthToken;
use App\Models\UserApp;
use App\Models\StatsCache;
use App\Services\LocaleResolver;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppsController extends Controller
{
    /**
     * GET /apps
     * 認証ユーザーの登録アプリ一覧を取得
     */
    public function getAppList(Request $request): JsonResponse
    {
        // 認証ユーザーの取得（ミドルウェアでCSRF認証済み）
        $user = $request->user();
        /*
        Log::debug('AppsController@getAppList', [
            'userId' => $user->id,
            'userName' => $user->name,
        ]);
        */
        $lang = LocaleResolver::resolve($request, $user);
        if (!$user) {
            return response()->json([
                'state' => 'error',
                'message' => trans('auth.unauthorized', [], $lang),
                'apps' => [],
            ], 401);
        }

        // ユーザーに紐付くアプリ一覧（リレーション経由で取得）
        // - AppモデルとUserモデルは多対多(user_apps中間テーブル)
        $apps = $user->apps()
            ->orderBy('created_at', 'desc')
            ->get();

        // レスポンス整形（必要に応じて属性変換）
        $appsList = $apps->map(function ($app) {
            return [
                'appId'           => $app->app_key, // ULID等で一意
                'name'            => $app->name,
                'url'             => $app->url,
                'description'     => $app->description,
                'date_update_time'=> $app->date_update_time,
                'sync_update_time'=> $app->sync_update_time,
                'currency_unit'   => $app->currency_unit,
                'pity_system'     => $app->pity_system,
                'guarantee_count' => $app->guarantee_count,
                'rarity_defs'     => $app->rarity_defs,
                'marker_defs'     => $app->marker_defs,
                'task_defs'       => $app->task_defs,
                'created_at'      => optional($app->created_at)->toIso8601String(),
                'updated_at'      => optional($app->updated_at)->toIso8601String(),
            ];
        })->values();

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

        // バリデーション
        $validator = Validator::make($request->all(), [
            'name'            => 'required|string|max:128',
            'url'             => 'nullable|url|max:255',
            'description'     => 'nullable|string|max:400',
            'date_update_time'=> 'nullable|string|max:8',
            'sync_update_time'=> 'nullable|boolean',
            'currency_unit'   => 'nullable|string|max:8',
            'pity_system'     => 'nullable|boolean',
            'guarantee_count' => 'nullable|integer',
            'rarity_defs'     => 'nullable|array',
            'marker_defs'     => 'nullable|array',
            'task_defs'       => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('auth.validation_failed', ['error' => $validator->errors()->first()], $lang),
            ], 400);
        }

        // AppId生成（要ULID・一意性担保）
        $appId = (string) Str::ulid();

        DB::beginTransaction();
        try {
            // アプリ新規登録
            $app = App::create([
                'app_key'         => $appId,
                'name'            => $request->input('name'),
                'url'             => $request->input('url'),
                'description'     => $request->input('description'),
                'date_update_time'=> $request->input('date_update_time'),
                'sync_update_time'=> $request->input('sync_update_time', false),
                'currency_unit'   => $request->input('currency_unit'),
                'pity_system'     => $request->input('pity_system', false),
                'guarantee_count' => $request->input('guarantee_count'),
                'rarity_defs'     => $request->input('rarity_defs', []),
                'marker_defs'     => $request->input('marker_defs', []),
                'task_defs'       => $request->input('task_defs', []),
            ]);

            // ユーザーとアプリの紐付け（中間テーブル）
            $user->apps()->attach($app->id, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            // レスポンスデータを整形
            $response = [
                'appId'           => $app->app_key,
                'name'            => $app->name,
                'url'             => $app->url,
                'description'     => $app->description,
                'date_update_time'=> $app->date_update_time,
                'sync_update_time'=> $app->sync_update_time,
                'currency_unit'   => $app->currency_unit,
                'pity_system'     => $app->pity_system,
                'guarantee_count' => $app->guarantee_count,
                'rarity_defs'     => $app->rarity_defs,
                'marker_defs'     => $app->marker_defs,
                'task_defs'       => $app->task_defs,
                'created_at'      => optional($app->created_at)->toIso8601String(),
                'updated_at'      => optional($app->updated_at)->toIso8601String(),
            ];

            return response()->json($response, 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json([
                'state'   => 'error',
                'message' => trans('messages.app_registration_failed', ['error' => $e->getMessage()]),
            ], 500);
        }
    }
    /**
     * GET /apps/{appId}
     * 認証ユーザーの指定アプリ情報を取得
     */
    public function getAppData(Request $request, string $appId): JsonResponse
    {
        // ユーザー認証情報取得
        $user = $request->user();
        $lang = LocaleResolver::resolve($request, $user);

        if (!$user) {
            return response()->json([
                'state' => 'error',
                'message' => trans('auth.unauthorized', [], $lang),
            ], 401);
        }

        // ユーザーが所有するアプリのうち該当app_keyを持つものを取得
        $app = $user->apps()
            ->where('app_key', $appId)
            ->first();

        if (!$app) {
            // アプリが存在しない場合 or 他ユーザーのアプリの場合も同様
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.app_not_found', [], $lang),
            ], 404);
        }

        // レスポンスデータ整形（getAppListの1件分と同じ形）
        $appData = [
            'appId'           => $app->app_key,
            'name'            => $app->name,
            'url'             => $app->url,
            'description'     => $app->description,
            'date_update_time'=> $app->date_update_time,
            'sync_update_time'=> $app->sync_update_time,
            'currency_unit'   => $app->currency_unit,
            'pity_system'     => $app->pity_system,
            'guarantee_count' => $app->guarantee_count,
            'rarity_defs'     => $app->rarity_defs,
            'marker_defs'     => $app->marker_defs,
            'task_defs'       => $app->task_defs,
            'created_at'      => optional($app->created_at)->toIso8601String(),
            'updated_at'      => optional($app->updated_at)->toIso8601String(),
        ];

        return response()->json($appData, 200);
    }
    /**
     * PUT /apps/{appId}
     * 認証ユーザーの指定アプリ情報を更新
     */
    public function updateApp(Request $request, string $appId): JsonResponse
    {
        $user = $request->user();
        $lang = LocaleResolver::resolve($request, $user);
        /*
        Log::debug('AppsController@updateApp', [
            'userId' => $user->id,
            'appId' => $appId,
        ]);
        */

        if (!$user) {
            return response()->json([
                'state' => 'error',
                'message' => trans('auth.unauthorized', [], $lang),
            ], 401);
        }

        // 指定されたappIdのアプリをユーザー所有分から取得
        $app = $user->apps()
            ->where('app_key', $appId)
            ->first();

        if (!$app) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.app_not_found', [], $lang),
            ], 404);
        }

        // バリデーション
        $validator = Validator::make($request->all(), [
            'name'            => 'required|string|max:128',
            'url'             => 'nullable|url|max:255',
            'description'     => 'nullable|string|max:400',
            'date_update_time'=> 'nullable|string|max:8',
            'sync_update_time'=> 'nullable|boolean',
            'currency_unit'   => 'nullable|string|max:8',
            'pity_system'     => 'nullable|boolean',
            'guarantee_count' => 'nullable|integer',
            'rarity_defs'     => 'nullable|array',
            'marker_defs'     => 'nullable|array',
            'task_defs'       => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'state' => 'error',
                'message' => trans('auth.validation_failed', ['error' => $validator->errors()->first()], $lang),
            ], 400);
        }

        // 更新
        DB::beginTransaction();
        try {
            $app->fill([
                'name'            => $request->input('name'),
                'url'             => $request->input('url'),
                'description'     => $request->input('description'),
                'date_update_time'=> $request->input('date_update_time'),
                'sync_update_time'=> $request->input('sync_update_time', false),
                'currency_unit'   => $request->input('currency_unit'),
                'pity_system'     => $request->input('pity_system', false),
                'guarantee_count' => $request->input('guarantee_count'),
                'rarity_defs'     => $request->input('rarity_defs', []),
                'marker_defs'     => $request->input('marker_defs', []),
                'task_defs'       => $request->input('task_defs', []),
            ]);
            $app->save();

            DB::commit();

            // レスポンス整形
            $response = [
                'appId'           => $app->app_key,
                'name'            => $app->name,
                'url'             => $app->url,
                'description'     => $app->description,
                'date_update_time'=> $app->date_update_time,
                'sync_update_time'=> $app->sync_update_time,
                'currency_unit'   => $app->currency_unit,
                'pity_system'     => $app->pity_system,
                'guarantee_count' => $app->guarantee_count,
                'rarity_defs'     => $app->rarity_defs,
                'marker_defs'     => $app->marker_defs,
                'task_defs'       => $app->task_defs,
                'created_at'      => optional($app->created_at)->toIso8601String(),
                'updated_at'      => optional($app->updated_at)->toIso8601String(),
            ];

            return response()->json($response, 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json([
                'state'   => 'error',
                'message' => trans('messages.app_update_failed', ['error' => $e->getMessage()], $lang),
            ], 500);
        }
    }
    /**
     * DELETE /apps/{appId}
     * 認証ユーザーの指定アプリを物理削除
     */
    public function deleteApp(Request $request, string $appId): JsonResponse
    {
        $user = $request->user();
        $lang = LocaleResolver::resolve($request, $user);

        if (!$user) {
            return response()->json([
                'state' => 'error',
                'message' => trans('auth.unauthorized', [], $lang),
            ], 401);
        }

        // ユーザー所有アプリかつ該当app_key
        $app = $user->apps()->where('app_key', $appId)->first();

        if (!$app) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.app_not_found', [], $lang),
            ], 404);
        }

        DB::beginTransaction();
        try {
            $userId = $user->id;
            $appKey = $app->app_key;
            $appDbId = $app->id;

            // user_apps の紐付け削除
            $user->apps()->detach($appDbId);

            // StatsCache の該当キャッシュ削除
            StatsCache::where('user_id', $userId)
                ->where('cache_key', 'like', "stats:{$userId}:{$appKey}:%")
                ->delete();

            \App\Models\Log::where('user_id', $userId)
                ->where('app_id', $appDbId)
                ->delete();

            // apps テーブル本体の物理削除
            $app->delete();

            DB::commit();

            return response()->json([
                'state' => 'success',
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('deleteApp error', ['error' => $e->getMessage()]);
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.app_delete_failed', ['error' => $e->getMessage()], $lang),
            ], 500);
        }
    }


}
