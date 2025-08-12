<?php

namespace App\Http\Controllers\Api\Logs;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\UserApp;
use App\Models\Log as LogModel;
use App\Services\LocaleResolver;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
//use Illuminate\Support\Facades\DB;
//use Illuminate\Support\Facades\Log;

class LogsController extends Controller
{
    /**
     * GET /logs/{appKey}?from=YYYY-MM-DD&to=YYYY-MM-DD&limit=number&offset=number&dir=asc|desc
     * 指定アプリのログを取得
     * - 「最新N件」は dir=desc（既定） + ORDER BY log_date DESC, id ASC で実現
     */
    public function index(Request $request, string $appKey): JsonResponse
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

        // ユーザーがアプリに紐付いているか確認
        $userApp = UserApp::where('user_id', $userId)
            ->where('app_id', $app->id)
            ->first();
        if (!$userApp) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.log_access_denied', [], $lang),
            ], 403);
        }

        // クエリパラメータ取得
        $from   = $request->query('from');// Y-m-d
        $to     = $request->query('to');// Y-m-d
        $limit  = $request->integer('limit') ?: null;
        $offset = $request->integer('offset') ?: null;
        $dir    = strtolower($request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // 日付バリデーション（Y-m-d のみ許容）
        foreach (['from' => $from, 'to' => $to] as $field => $val) {
            if ($val !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                return response()->json([
                    'state' => 'error',
                    'message' => trans('messages.invalid_date_format', ['field' => $field], $lang),
                ], 400);
            }
        }
        /* 旧バリデーション
        $fromDate = null;
        $toDate = null;
        if ($from) {
            try {
                $fromDate = Carbon::createFromFormat('Y-m-d', $from)->startOfDay();
            } catch (\Exception $e) {
                return response()->json([
                    'state' => 'error',
                    'message' => trans('messages.invalid_date_format', ['field' => 'from'], $lang),
                ], 400);
            }
        }
        if ($to) {
            try {
                $toDate = Carbon::createFromFormat('Y-m-d', $to)->endOfDay();
            } catch (\Exception $e) {
                return response()->json([
                    'state' => 'error',
                    'message' => trans('messages.invalid_date_format', ['field' => 'to'], $lang),
                ], 400);
            }
        }
        */

        // クエリ組み立て
        $query = LogModel::where('user_id', $userId)
            ->where('app_id', $app->id);

        // 範囲条件（date のまま比較）
        if ($from !== null) {
            $query->where('log_date', '>=', $from);
        }
        if ($to !== null) {
            $query->where('log_date', '<=', $to);
        }

        // 並び順：最新N件の既定 => DESC。tie-breaker で id ASC を必ず入れる
        $query->orderBy('log_date', $dir)
              ->orderBy('id', 'asc');

        // limit/offset
        if ($offset !== null) {
            $query->offset($offset);
        }
        if ($limit !== null) {
            $query->limit($limit);
        }

        // ログ取得
        $logs = $query->get();

        // ログを整形
        $logs = $logs->map(function ($log) use ($appKey) {
            return [
                'appId' => $appKey,
                'date' => Carbon::parse($log->log_date)->format('Y-m-d'),
                'total_pulls' => $log->total_pulls,
                'discharge_items' => $log->discharge_items,
                'expense' => $log->expense,
                'drop_details' => $log->drop_details,
                'tags' => $log->tags,
                'free_text' => $log->free_text ?? '',
                'images' => $log->images,
                'tasks' => $log->tasks,
                'last_updated' => $log->updated_at->toIso8601String(),
            ];
        });
        /*
        Log::debug('LogsController@index', [
            'userId' => $userId,
            'appKey' => $appKey,
            'from' => $from,
            'to' => $to,
            'limit' => $limit,
            'offset' => $offset,
            'logCount' => $logs->count(),
            'logs' => $logs,
        ]);
        */

        // レスポンス
        return response()->json($logs);
    }

    
}
