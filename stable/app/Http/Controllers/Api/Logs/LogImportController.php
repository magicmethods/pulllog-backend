<?php

namespace App\Http\Controllers\Api\Logs;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\UserApp;
use App\Models\Log as LogModel;
use App\Services\LocaleResolver;
use App\Support\Cache\StatsCachePurger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log as Logger;

class LogImportController extends Controller
{
    use StatsCachePurger;

    private const MAX_ROWS = 10000;
    private const UPSERT_CHUNK = 2000;

    /**
     * POST /logs/import/{appKey}?mode=overwrite|merge
     * - ファイル: multipart/form-data の file フィールド
     * - フォーマット: PullLog JSON/CSV（旧キー互換: camelCase / snake_case）
     * - 金額:
     *   - v2: expense_amount（最小単位の整数）
     *   - 互換: expense（最小単位の整数）/ expense_decimal（小数→minor_unit で整数化）
     */
    public function import(Request $request, string $appKey): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;
        $lang = LocaleResolver::resolve($request, $user);

        // アプリ解決 & 所有チェック
        $app = App::where('app_key', $appKey)->with('currency')->first();
        if (!$app) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('messages.app_not_found', [], $lang),
            ], 404);
        }
        $has = UserApp::where('user_id', $userId)->where('app_id', $app->id)->exists();
        if (!$has) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('messages.log_access_denied', [], $lang),
            ], 403);
        }

        // モード
        $mode = $request->query('mode');
        if (!in_array($mode, ['overwrite', 'merge'], true)) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('messages.import_invalid_mode', [], $lang),
            ], 400);
        }

        // ファイル検証（1MB, CSV/JSON）
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'max:1024', 'mimes:csv,txt,json'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('messages.import_file_invalid', [], $lang),
                'errors'  => $validator->errors(),
            ], 422);
        }
        $uploaded = $request->file('file');
        $ext = strtolower($uploaded->getClientOriginalExtension());

        // パース
        try {
            $raw = match ($ext) {
                'json'      => $this->parseJson($uploaded->getRealPath()),
                'csv', 'txt'=> $this->parseCsv($uploaded->getRealPath()),
                default     => throw new \RuntimeException('Invalid file format'),
            };
        } catch (\Throwable $e) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('messages.import_parse_failed', ['msg' => $e->getMessage()], $lang),
            ], 400);
        }

        if (!is_array($raw) || count($raw) === 0) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('messages.import_no_data', [], $lang),
            ], 400);
        }

        // minor_unit で整数化（expense_decimal -> expense_amount 変換用）
        $minorUnit = (int)optional($app->currency)->minor_unit ?? 0;
        $pow = $minorUnit > 0 ? (10 ** $minorUnit) : 1;
        $now = Carbon::now('UTC');

        $rows = [];
        foreach ($raw as $i => $row) {
            if ($i >= self::MAX_ROWS) break;

            // 日付
            $date = $row['date'] ?? null;
            if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }

            // 金額（優先順: expense_amount → expense_decimal → expense）
            // - expense_amount: そのまま整数
            // - expense_decimal: minor_unitで整数化
            // - expense: 旧整数キー（最小単位）を流用
            $expenseAmount = null;
            if (isset($row['expense_amount'])) {
                $expenseAmount = (int) $row['expense_amount'];
            } elseif (isset($row['expenseAmount'])) {
                $expenseAmount = (int) $row['expenseAmount'];
            } elseif (isset($row['expense_decimal']) || isset($row['expenseDecimal'])) {
                $dec = $row['expense_decimal'] ?? $row['expenseDecimal'];
                $n = is_numeric($dec) ? (float) $dec : 0.0;
                $expenseAmount = (int) max(0, round($n * $pow));
            } elseif (isset($row['expense'])) {
                $expenseAmount = (int) $row['expense'];
            } else {
                $expenseAmount = 0;
            }

            // 配列系（文字列で来てもOKにしてデコード）
            $dropDetails = $row['drop_details'] ?? $row['dropDetails'] ?? [];
            if (is_string($dropDetails)) $dropDetails = json_decode($dropDetails, true) ?: [];
            $tags = $row['tags'] ?? [];
            if (is_string($tags)) $tags = json_decode($tags, true) ?: [];
            $images = $row['images'] ?? [];
            if (is_string($images)) $images = json_decode($images, true) ?: [];
            $tasks = $row['tasks'] ?? [];
            if (is_string($tasks)) $tasks = json_decode($tasks, true) ?: [];

            $rows[] = [
                'user_id'         => $userId,
                'app_id'          => $app->id,
                'log_date'        => $date,
                'total_pulls'     => (int) ($row['total_pulls'] ?? $row['totalPulls'] ?? 0),
                'discharge_items' => (int) ($row['discharge_items'] ?? $row['dischargeItems'] ?? 0),
                'expense_amount'  => max(0, (int) $expenseAmount),
                // JSONB カラムは DB::table() 直挿しでは文字列にする（PDOの Array to string conversion を回避）
                'drop_details'    => json_encode($dropDetails, JSON_UNESCAPED_UNICODE),
                'tags'            => json_encode($tags, JSON_UNESCAPED_UNICODE),
                'free_text'       => (string) ($row['free_text'] ?? $row['freeText'] ?? ''),
                'images'          => json_encode($images, JSON_UNESCAPED_UNICODE),
                'tasks'           => json_encode($tasks, JSON_UNESCAPED_UNICODE),
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        if (empty($rows)) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('messages.import_no_valid_data', [], $lang),
            ], 400);
        }

        // DB 保存
        DB::beginTransaction();
        try {
            if ($mode === 'overwrite') {
                // 全削除 → バルク挿入
                LogModel::where('user_id', $userId)->where('app_id', $app->id)->delete();
                // 大量でも分割して insert
                foreach (array_chunk($rows, self::UPSERT_CHUNK) as $chunk) {
                    DB::table('logs')->insert($chunk);
                }
            } else {
                // マージ（アップサート）
                $conflict = ['user_id', 'app_id', 'log_date']; // UNIQUEキー
                $updateCols = [
                    'total_pulls', 'discharge_items', 'expense_amount',
                    'drop_details', 'tags', 'free_text', 'images', 'tasks', 'updated_at'
                ];
                foreach (array_chunk($rows, self::UPSERT_CHUNK) as $chunk) {
                    DB::table('logs')->upsert($chunk, $conflict, $updateCols);
                }
            }

            // 統計キャッシュパージ（新旧キー両対応）
            $this->purgeStatsCacheForApp($userId, $app->id, $app->app_key);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'state'   => 'error',
                'message' => trans('messages.import_save_failed', ['msg' => $e->getMessage()], $lang),
            ], 500);
        }

        return response()->json([
            'state' => 'success',
            'count' => count($rows),
        ]);
    }

    /**
     * JSON パース（PullLog エクスポート形式）
     */
    private function parseJson(string $path): array
    {
        $content = file_get_contents($path);
        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new \RuntimeException('JSON parse error');
        }
        return $data;
    }

    /**
     * CSV パース（PullLog エクスポート形式）
     * 既存列名のキャメル/スネーク両対応（存在するものだけ取り込む）
     */
    private function parseCsv(string $path): array
    {
        $h = fopen($path, 'r');
        if ($h === false) throw new \RuntimeException('CSV read failed');

        $header = fgetcsv($h);
        if (!is_array($header)) {
            fclose($h);
            throw new \RuntimeException('CSV header error');
        }
        // ヘッダ正規化（先頭・末尾の空白を除去）
        $header = array_map(fn($c) => trim((string)$c), $header);

        $logs = [];
        while (($row = fgetcsv($h)) !== false) {
            $assoc = array_combine($header, $row);

            // 代表キー（最低限）
            $logs[] = array_filter([
                'date'             => $assoc['date'] ?? null,
                'totalPulls'       => $assoc['totalPulls'] ?? $assoc['total_pulls'] ?? null,
                'dischargeItems'   => $assoc['dischargeItems'] ?? $assoc['discharge_items'] ?? null,
                // 金額（v2優先で拾えるように）
                'expense_amount'   => $assoc['expense_amount'] ?? null,
                'expenseDecimal'   => $assoc['expenseDecimal'] ?? $assoc['expense_decimal'] ?? null,
                'expense'          => $assoc['expense'] ?? null,
                // JSON 系
                'dropDetails'      => $assoc['dropDetails'] ?? $assoc['drop_details'] ?? null,
                'tags'             => $assoc['tags'] ?? null,
                'freeText'         => $assoc['freeText'] ?? $assoc['free_text'] ?? null,
                'images'           => $assoc['images'] ?? null,
                'tasks'            => $assoc['tasks'] ?? null,
            ], fn($v) => $v !== null);
        }

        fclose($h);
        return $logs;
    }
}
