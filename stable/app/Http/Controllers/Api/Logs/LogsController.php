<?php

namespace App\Http\Controllers\Api\Logs;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\Log;
use App\Services\LocaleResolver;
use App\Support\Responses\LogResponse;
use App\Services\Demo\DemoLogProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LogsController extends Controller
{
    /**
     * GET /logs/{appKey}?from=YYYY-MM-DD&to=YYYY-MM-DD&limit=number&offset=number&dir=asc|desc
     * 指定アプリのログ一覧を取得
     * - 最新N件 => dir=desc（既定） + ORDER BY log_date DESC, id ASC
     */
    public function index(Request $request, string $appKey): JsonResponse
    {
        $user = $request->user();
        $lang = LocaleResolver::resolve($request, $user);

        // app_key → app 解決
        $app = App::where('app_key', $appKey)->first();
        if (!$app) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('messages.app_not_found', [], $lang),
            ], 404);
        }

        // 所有確認（中間テーブル経由）
        if (!$user->apps()->where('apps.id', $app->id)->exists()) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('messages.log_access_denied', [], $lang),
            ], 403);
        }

        // クエリパラメータ
        $from   = $request->query('from');   // Y-m-d
        $to     = $request->query('to');     // Y-m-d
        $limit  = $request->filled('limit')  ? max(0, (int) $request->query('limit'))  : null;
        $offset = $request->filled('offset') ? max(0, (int) $request->query('offset')) : null;
        $dir    = strtolower($request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // 日付フォーマットの軽量バリデーション
        foreach (['from' => $from, 'to' => $to] as $field => $val) {
            if ($val !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                return response()->json([
                    'state'   => 'error',
                    'message' => trans('messages.invalid_date_format', ['field' => $field], $lang),
                ], 400);
            }
        }

        if ($this->isDemoUser($user)) {
            $provider = app(DemoLogProvider::class);

            // 期間決定：指定が無ければ「直近30日」
            $toDate   = $request->query('to')   ? Carbon::createFromFormat('Y-m-d', $request->query('to'))->startOfDay()   : Carbon::today();
            $fromDate = $request->query('from') ? Carbon::createFromFormat('Y-m-d', $request->query('from'))->startOfDay() : $toDate->clone()->subDays(29);

            $rows = $provider->generateRange((int)$app->id, $fromDate, $toDate);

            // 並び順
            $dir = strtolower($request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
            $rows = $rows->sortBy('date', SORT_REGULAR, $dir === 'desc')->values();

            // offset/limit
            $offset = $request->filled('offset') ? max(0, (int)$request->query('offset')) : null;
            $limit  = $request->filled('limit')  ? max(0, (int)$request->query('limit'))  : null;
            if ($offset !== null) $rows = $rows->slice($offset)->values();
            if ($limit  !== null) $rows = $rows->take($limit)->values();

            // LogResponse へ整形（未保存モデルに詰め替え）
            $payload = $rows->map(function (array $row) use ($user, $app) {
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
                return LogResponse::toArray($log, $app);
            })->values();

            return response()->json($payload, 200);
        }
        
        // クエリ組み立て
        $query = Log::where('user_id', $user->id)
            ->where('app_id', $app->id);

        if ($from !== null) $query->where('log_date', '>=', $from);
        if ($to   !== null) $query->where('log_date', '<=', $to);

        $query->orderBy('log_date', $dir)
              ->orderBy('id', 'asc');

        if ($offset !== null) $query->offset($offset);
        if ($limit  !== null) $query->limit($limit);

        // 取得 & 整形（expense_decimal を含める）
        $logs = $query->get()->map(fn ($log) => LogResponse::toArray($log, $app))->values();

        return response()->json($logs, 200);
    }

    // デモユーザーかどうかを判定
    private function isDemoUser($user): bool
    {
        $demoEmail = (string) config('demo.demo_email', env('DEMO_EMAIL'));
        if ($demoEmail !== '' && \strtolower($user->email ?? '') === \strtolower($demoEmail)) return true;
        $demoUserIds = (array) config('demo.demo_user_ids', []);
        if (!empty($demoUserIds) && \in_array((int)$user->id, array_map('intval', $demoUserIds), true)) return true;
        if (property_exists($user, 'roles') && \in_array('demo', $user->roles, true)) return true;
        return false;
    }

}
