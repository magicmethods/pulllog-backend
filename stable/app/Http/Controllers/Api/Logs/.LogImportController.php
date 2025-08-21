<?php

namespace App\Http\Controllers\Api\Logs;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\UserApp;
use App\Models\Log;
use App\Services\LocaleResolver;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log as Logger;

class LogImportController extends Controller
{
    /**
     * POST /logs/import/{app}
     * 指定アプリのログをインポート
     * @param Request $request
     * @param string $appKey
     */
    public function import(Request $request, string $appKey): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;
        $lang = LocaleResolver::resolve($request, $user);

        // アプリ取得＆権限チェック
        $app = App::where('app_key', $appKey)->first();
        if (!$app) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.app_not_found', [], $lang),
            ], 404);
        }
        $userApp = UserApp::where('user_id', $userId)->where('app_id', $app->id)->first();
        if (!$userApp) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.log_access_denied', [], $lang),
            ], 403);
        }

        /*
        Logger::info('LogImportController@import', [
            'user_id' => $userId,
            'app_id' => $app->id,
            'request' => $request->all(),
        ]);
        */
        
        // クエリパラメータ mode
        $mode = $request->query('mode');
        if (!in_array($mode, ['overwrite', 'merge'], true)) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.import_invalid_mode', [], $lang),
            ], 400);
        }

        // ファイルバリデーション（1MB上限, CSV/JSON限定）
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'max:1024', 'mimes:csv,txt,json'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.import_file_invalid', [], $lang),
                'errors' => $validator->errors(),
            ], 422);
        }
        $uploadedFile = $request->file('file');
        $ext = strtolower($uploadedFile->getClientOriginalExtension());

        /*
        Logger::info('LogImportController@import', [
            'validator' => $validator->errors(),
            'uploadedFile' => $uploadedFile->getClientOriginalName(),
            'extension' => $ext,
        ]);
        */
        
        // パース
        try {
            $parsedLogs = match ($ext) {
                'json' => $this->parseJson($uploadedFile->getRealPath()),
                'csv', 'txt' => $this->parseCsv($uploadedFile->getRealPath()),
                default => throw new \Exception('Invalid file format'),
            };
        } catch (\Throwable $e) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.import_parse_failed', ['msg' => $e->getMessage()], $lang),
            ], 400);
        }

        if (!is_array($parsedLogs) || count($parsedLogs) === 0) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.import_no_data', [], $lang),
            ], 400);
        }

        /*
        Logger::info('LogImportController@import', [
            'parsedLogs' => $parsedLogs,
        ]);
        */

        // 整形＆型安全バリデーション
        $now = Carbon::now('UTC');
        $logsToUpsert = [];
        $maxRows = 10000;
        foreach ($parsedLogs as $i => $log) {
            if ($i >= $maxRows) break;
            $date = $log['date'] ?? null;
            if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;

            $dropDetails = $log['drop_details'] ?? $log['dropDetails'] ?? [];
            if (is_string($dropDetails)) {
                $dropDetails = json_decode($dropDetails, true) ?: [];
            }
            $tags = $log['tags'] ?? [];
            if (is_string($tags)) {
                $tags = json_decode($tags, true) ?: [];
            }
            $logsToUpsert[] = [
                'user_id'         => $userId,
                'app_id'          => $app->id,
                'log_date'        => $date,
                'total_pulls'     => (int)($log['total_pulls'] ?? $log['totalPulls'] ?? 0),
                'discharge_items' => (int)($log['discharge_items'] ?? $log['dischargeItems'] ?? 0),
                'expense'         => (int)($log['expense'] ?? 0),
                'drop_details'    => json_encode($dropDetails),
                'tags'            => json_encode($tags),
                'free_text'       => $log['free_text'] ?? $log['freeText'] ?? '',
                'images'          => json_encode($log['images'] ?? []),
                'tasks'           => json_encode($log['tasks'] ?? []),
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }
        if (empty($logsToUpsert)) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.import_no_valid_data', [], $lang),
            ], 400);
        }

        /*
        Logger::info('LogImportController@import', [
            'logs' => $logsToUpsert,
        ]);
        DB::listen(function($query) {
            Logger::info('SQL', ['sql' => $query->sql, 'bindings' => $query->bindings]);
        });
        */

        // DB登録
        DB::beginTransaction();
        try {
            if ($mode === 'overwrite') {
                Log::where('user_id', $userId)->where('app_id', $app->id)->delete();
                // 直接DBに挿入（バルクインサート）
                DB::table('logs')->insert($logsToUpsert);
            } else {
                // マージモード: 既存データを更新、なければ挿入
                // 直接DBにアップサート（PostgreSQL 9.5+）
                DB::table('logs')->upsert(
                    $logsToUpsert,
                    ['user_id', 'app_id', 'log_date'], // ユニークキー
                    ['total_pulls', 'discharge_items', 'expense', 'drop_details', 'tags', 'free_text', 'images', 'tasks', 'updated_at']
                );
            }
            // 統計キャッシュ削除
            $this->deleteStatsCache($userId, $appKey);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            /*
            Logger::debug('LogImportController@import.error', [
                'error' => $e,
            ]);
            */
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.import_save_failed', ['msg' => $e->getMessage()], $lang),
            ], 500);
        }

        // 成功
        return response()->json([
            'state' => 'success',
            'count' => count($logsToUpsert),
        ]);
    }
    /**
     * JSONパース: エクスポート済PullLog形式
     */
    private function parseJson(string $path): array
    {
        $content = file_get_contents($path);
        $data = json_decode($content, true);
        if (!is_array($data)) throw new \Exception('JSON parse error');
        return $data;
    }
    /**
     * CSVパース: エクスポート済PullLog形式
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) throw new \Exception('CSV read failed');
        $header = fgetcsv($handle);
        if (!is_array($header)) throw new \Exception('CSV header error');
        $logs = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rowAssoc = array_combine($header, $row);
            // カラム名変換 PullLog形式対応
            $logs[] = [
                'date' => $rowAssoc['date'] ?? null,
                'totalPulls' => $rowAssoc['totalPulls'] ?? 0,
                'dischargeItems' => $rowAssoc['dischargeItems'] ?? 0,
                'expense' => $rowAssoc['expense'] ?? 0,
                'dropDetails' => $rowAssoc['dropDetails'] ?? '[]',
                'tags' => $rowAssoc['tags'] ?? '[]',
                'freeText' => $rowAssoc['freeText'] ?? '',
            ];
        }
        fclose($handle);
        return $logs;
    }
    /**
     * 対象キャッシュを一括削除
     */
    private function deleteStatsCache(int $userId, string $appKey)
    {
        $pattern = "stats:{$userId}:{$appKey}:%";
        \App\Models\StatsCache::where('cache_key', 'like', $pattern)->delete();
    }

}
