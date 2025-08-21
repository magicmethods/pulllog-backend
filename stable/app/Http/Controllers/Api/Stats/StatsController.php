<?php

namespace App\Http\Controllers\Api\Stats;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\LogWithMoney;
use App\Models\StatsCache;
use App\Models\UserApp;
use App\Services\Demo\DemoLogProvider;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Services\LocaleResolver;
use Illuminate\Support\Facades\Log;

class StatsController extends Controller
{
    /**
     * GET /stats/{app}
     * - {app} は 数値ID or app_key（ULID）のどちらでも可
     * - ?start=YYYY-MM-DD, ?end=YYYY-MM-DD で絞り込み
     * - 実ユーザー: logs_with_money ビュー（LogWithMoney）で集計（expense_decimal）
     * - デモユーザー: DemoLogProvider で期間データを合成し同様の指標を返す
     */
    public function getAppStats(Request $request, string $app): JsonResponse|Response
    {
        // 認証ユーザー
        $user   = $request->user();
        $userId = $user->id;
        $lang = LocaleResolver::resolve($request, $user);

        // 対象アプリ解決（ID or app_key）
        $appModel = $this->resolveApp($app);
        if (!$appModel) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('messages.app_not_found', [], $lang),
            ], 404);
        }

        // 所有確認
        $owned = UserApp::where('user_id', $userId)->where('app_id', $appModel->id)->exists();
        if (!$owned) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('messages.app_not_registered', [], $lang),
            ], 403);
        }

        // 日付パラメータの正規化（YYYY-MM-DD のみ許可／不正なら 400）
        [$startDate, $endDate, $dateError] = $this->normalizeDateRange(
            $request->query('start'),
            $request->query('end')
        );
        if ($dateError) {
            return response()->json([
                'state'   => 'error',
                'message' => $dateError,
            ], 400);
        }

        // 通貨の少数桁（無い場合は2にフォールバック）
        $minorUnit = (int) optional($appModel->currency)->minor_unit ?? 2;
        $precision = max(0, min(6, $minorUnit)); // view は NUMERIC(38,6) なので上限6に丸める

        // ------------- デモユーザー分岐 -------------
        if ($this->isDemoUser($user)) {
            // 期間決定：未指定なら直近30日（end=today, start=end-29d）
            $end = $endDate ? Carbon::parse($endDate) : Carbon::today();
            $start = $startDate ? Carbon::parse($startDate) : $end->clone()->subDays(29);

            /** @var DemoLogProvider $provider */
            $provider = app(DemoLogProvider::class);
            // DemoLogProvider は expense_amount（整数, minor 単位）を返す想定
            $rows = $provider->generateRange((int)$appModel->id, $start, $end);

            if ($rows->isEmpty()) {
                return response('null', 200, ['Content-Type' => 'application/json']);
            }

            // 集計（expense_decimal = expense_amount / 10^minorUnit）
            $divisor = pow(10, $minorUnit);

            $totalPulls    = (int) $rows->sum(fn($r) => (int)($r['total_pulls'] ?? 0));
            $rareDropCount = (int) $rows->sum(fn($r) => (int)($r['discharge_items'] ?? 0));
            $totalExpense  = (float) $rows->sum(function ($r) use ($divisor) {
                $amount = (int)($r['expense_amount'] ?? 0);
                return $divisor > 0 ? ($amount / $divisor) : (float)$amount;
            });

            // min/max 日付（rows は 'YYYY-MM-DD'）
            $statsStartDate = $rows->min('date');
            $statsEndDate   = $rows->max('date');

            // 月別支出（YYYY-MM → sum(expense_decimal)）
            $monthlyExpense = $rows->groupBy(function ($r) {
                return substr((string)$r['date'], 0, 7); // 'YYYY-MM'
            })->map(function ($group) use ($divisor) {
                $sum = 0.0;
                foreach ($group as $r) {
                    $sum += ((int)($r['expense_amount'] ?? 0)) / $divisor;
                }
                return $sum;
            })->sortKeys()->toArray();

            // メトリクス計算
            $rareDropRate           = $totalPulls === 0    ? 0.0 : round(($rareDropCount / $totalPulls) * 100, 2);
            $averageExpense         = $rareDropCount === 0 ? 0.0 : round($totalExpense / $rareDropCount, $precision);
            $averageRareDropRate    = $rareDropCount === 0 ? 0.0 : round($totalPulls / max(1, $rareDropCount), 2);
            $averageMonthlyExpense  = empty($monthlyExpense) ? 0.0 :
                round(array_sum($monthlyExpense) / count($monthlyExpense), $precision);

            $monthsInPeriod = $this->getIntervalMonths($statsStartDate, $statsEndDate);

            $response = [
                'appId'                 => $appModel->app_key,
                'currencyCode'          => $appModel->currency_code,
                'minorUnit'             => $minorUnit,

                'startDate'             => $statsStartDate,
                'endDate'               => $statsEndDate,
                'totalLogs'             => (int) $rows->count(),
                'monthsInPeriod'        => $monthsInPeriod,

                'totalPulls'            => $totalPulls,
                'rareDropCount'         => $rareDropCount,
                'rareDropRate'          => $rareDropRate,                         // %
                'totalExpense'          => round($totalExpense, $precision),      // major 単位
                'averageExpense'        => $averageExpense,                       // 1レアあたり
                'averageMonthlyExpense' => $averageMonthlyExpense,                // 1ヶ月あたり
                'averageRareDropRate'   => $averageRareDropRate,                  // 平均天井（pulls / rare）
                // 必要なら 'monthlyExpense' を返すことも可能
                'demo'                  => true,
            ];

            // デモはキャッシュに保存しない（混在防止）
            return response()->json($response);
        }

        // 以降、実ユーザー用処理

        // キャッシュキー生成（バージョン区別と通貨コードも追加）
        $cacheKey = sprintf(
            'stats:%s:u:%d:app:%d:%s:%s:cur:%s',
            config('cache.key_version'),
            $userId,
            $appModel->id,
            $startDate ?? 'null',
            $endDate   ?? 'null',
            $appModel->currency_code ?? 'UNK'
        );

        // 先にキャッシュを見る
        if ($cache = StatsCache::where('cache_key', $cacheKey)->first()) {
            Log::debug('StatsController@getAppStats', [
                'cacheKey' => $cacheKey,
                'cache' => $cache->value,
            ]);
            return response()->json(json_decode($cache->value, true));
        }

        // 集計クエリ（logs_with_money）
        $base = LogWithMoney::query()
            ->where('user_id', $userId)
            ->where('app_id',  $appModel->id);

        if ($startDate) $base->whereDate('log_date', '>=', $startDate);
        if ($endDate)   $base->whereDate('log_date', '<=', $endDate);

        // 期間全体の合計・最小/最大日
        $stats = (clone $base)
            ->selectRaw('MIN(log_date) AS min_date')
            ->selectRaw('MAX(log_date) AS max_date')
            ->selectRaw('COALESCE(SUM(total_pulls), 0)        AS total_pulls')
            ->selectRaw('COALESCE(SUM(discharge_items), 0)    AS rare_drop_count')
            ->selectRaw('COALESCE(SUM(expense_decimal), 0.0)  AS total_expense')
            ->first();

        Log::debug('StatsController@getAppStats', [
            'stats' => $stats,
        ]);
        // ログが一件もない
        if (!$stats || !$stats->min_date) {
            return response('null', 200, ['Content-Type' => 'application/json']);
        }

        // 月別支出（YYYY-MM → sum(expense_decimal)）
        $monthlyExpense = (clone $base)
            ->selectRaw("to_char(date_trunc('month', log_date), 'YYYY-MM') AS ym")
            ->selectRaw('COALESCE(SUM(expense_decimal), 0.0) AS amount')
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->pluck('amount', 'ym')
            ->toArray();

        // メトリクス計算
        $totalPulls       = (int) $stats->total_pulls;
        $rareDropCount    = (int) $stats->rare_drop_count;
        $totalExpense     = (float) $stats->total_expense;

        $rareDropRate         = $totalPulls === 0    ? 0.0 : round(($rareDropCount / $totalPulls) * 100, 2);
        $averageExpense       = $rareDropCount === 0 ? 0.0 : round($totalExpense / $rareDropCount, $precision);
        $averageRareDropRate  = $rareDropCount === 0 ? 0.0 : round($totalPulls / max(1, $rareDropCount), 2);
        $averageMonthlyExpense= empty($monthlyExpense) ? 0.0 :
            round(array_sum($monthlyExpense) / count($monthlyExpense), $precision);

        $statsStartDate  = Carbon::parse($stats->min_date)->format('Y-m-d');
        $statsEndDate    = Carbon::parse($stats->max_date)->format('Y-m-d');
        $monthsInPeriod  = $this->getIntervalMonths($statsStartDate, $statsEndDate);

        // 応答（従来互換: appId は app_key を返す）
        $response = [
            'appId'                 => $appModel->app_key,
            'currencyCode'          => $appModel->currency_code,
            'minorUnit'             => $minorUnit,

            'startDate'             => $statsStartDate,
            'endDate'               => $statsEndDate,
            'totalLogs'             => (int) (clone $base)->count(), // 件数
            'monthsInPeriod'        => $monthsInPeriod,

            'totalPulls'            => $totalPulls,
            'rareDropCount'         => $rareDropCount,
            'rareDropRate'          => $rareDropRate,           // %
            'totalExpense'          => round($totalExpense, $precision), // major単位の合計
            'averageExpense'        => $averageExpense,         // 1レアあたり
            'averageMonthlyExpense' => $averageMonthlyExpense,  // 1ヶ月あたり
            'averageRareDropRate'   => $averageRareDropRate,    // 平均天井（pulls / rare）
            // 必要なら 'monthlyExpense' を返すことも可能
        ];

        // キャッシュ保存
        StatsCache::updateOrCreate(
            ['cache_key' => $cacheKey],
            [
                'cache_key' => $cacheKey,
                'user_id'   => $userId,
                'value'     => json_encode($response, JSON_UNESCAPED_UNICODE),
            ]
        );

        return response()->json($response);
    }

    /**
     * ID 文字列 or ULID(app_key) を受け取り App を返す
     */
    private function resolveApp(string $idOrKey): ?App
    {
        if (ctype_digit($idOrKey)) {
            return App::find((int) $idOrKey);
        }
        return App::where('app_key', $idOrKey)->first();
    }

    /**
     * YYYY-MM-DD 形式に正規化して返す
     * @return array{0:?string,1:?string,2:?string} [start,end,errorMessage]
     */
    private function normalizeDateRange($start, $end): array
    {
        $startDate = $this->normalizeDate($start);
        $endDate   = $this->normalizeDate($end);

        if ($start !== null && $startDate === null) {
            return [null, null, 'Invalid start date. Expect YYYY-MM-DD'];
        }
        if ($end !== null && $endDate === null) {
            return [null, null, 'Invalid end date. Expect YYYY-MM-DD'];
        }
        if ($startDate !== null && $endDate !== null && $startDate > $endDate) {
            return [null, null, 'start must be <= end'];
        }
        return [$startDate, $endDate, null];
    }

    private function normalizeDate($val): ?string
    {
        if ($val === null) return null;
        $s = (string) $val;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return null;
        try {
            return Carbon::parse($s)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function getIntervalMonths(string $startDate, string $endDate): int
    {
        $d1 = Carbon::parse($startDate);
        $d2 = Carbon::parse($endDate);
        $diff = $d1->diff($d2);
        return ($diff->y * 12) + $diff->m + 1; // 端数切上げ +1（同月なら1）
    }

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
        if (property_exists($user, 'roles') && in_array('demo', $user->roles, true)) {
            return true;
        }
        return false;
    }

}
