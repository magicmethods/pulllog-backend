<?php

namespace App\Http\Controllers\Api\Stats;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Models\App;
use App\Models\UserApp;
use App\Models\StatsCache;
use App\Services\LocaleResolver;
//use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StatsController extends Controller
{
    /**
     * GET /stats/{appKey}
     * アプリの統計情報を取得
     */
    public function getAppStats(Request $request, $appKey): JsonResponse|Response
    {
        // 認証ユーザーの取得
        $user = $request->user();
        $userId = $user->id;
        $lang = LocaleResolver::resolve($request, $user);

        // app_key → app_id 解決
        $app = App::where('app_key', $appKey)->first();
        if (!$app) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.app_not_found', [], $lang),
            ], 404);
        }

        $appId = $app->id;

        // ユーザーがこのアプリを所有しているか確認
        if (!UserApp::where('user_id', $userId)->where('app_id', $appId)->exists()) {
            return response()->json([
                'state' => 'error',
                'message' => trans('messages.app_not_registered', [], $lang),
            ], 403);
        }

        // 日付範囲
        $startDate = $request->query('start');
        $endDate = $request->query('end');

        $logsAll = [];

        $query = \App\Models\Log::where('user_id', $userId)
            ->where('app_id', $appId);

        if ($startDate) {
            $query->where('log_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('log_date', '<=', $endDate);
        }
        $query->orderBy('log_date', 'asc');

        $logs = $query->get([
            'log_date', 'total_pulls', 'discharge_items', 'expense'
        ]);
        if ($logs->isNotEmpty()) {
            $logsAll = $logsAll ? $logsAll->concat($logs) : $logs;
        }

        if (empty($logsAll) || count($logsAll) === 0) {
            return response('null', 200, ['Content-Type' => 'application/json']);
        }

        // キャッシュキー生成
        $cacheKey = "stats:{$userId}:{$appKey}:{$startDate}:{$endDate}";
        // キャッシュ検索
        $cache = StatsCache::where('cache_key', $cacheKey)->first();
        if ($cache) {
            // キャッシュヒット
            return response()->json(json_decode($cache->value, true));
        }

        /*
        Log::debug('StatsController@getAppStats', [
            'userId' => $userId,
            'appKey' => $appKey,
            'appId'  => $appId,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'logsCount' => count($logsAll),
            'empty?' => empty($logsAll),
            'cacheKey' => $cacheKey,
            'cache' => $cache,
        ]);
        */

        // ソート
        $logsAll = $logsAll->sortBy('log_date')->values();

        // 集計
        $totalPulls = 0;
        $rareDropCount = 0;
        $totalExpense = 0;
        $monthlyExpense = [];

        foreach ($logsAll as $log) {
            $totalPulls += $log->total_pulls;
            $rareDropCount += $log->discharge_items;
            $totalExpense += $log->expense ?? 0;
            $yearMonth = Carbon::parse($log->log_date)->format('Y-m');
            if (!isset($monthlyExpense[$yearMonth])) {
                $monthlyExpense[$yearMonth] = 0;
            }
            $monthlyExpense[$yearMonth] += $log->expense ?? 0;
        }

        $statsStartDate = $logsAll->first()->log_date;
        $statsEndDate = $logsAll->last()->log_date;
        $monthsInPeriod = $this->getIntervalMonths($statsStartDate, $statsEndDate);

        $rareDropRate = $totalPulls === 0 ? 0 : round($rareDropCount / $totalPulls * 100, 2);
        $averageExpense = $rareDropCount === 0 ? 0 : round($totalExpense / $rareDropCount, 0);
        $averageRareDropRate = $rareDropCount === 0 ? 0 : round($totalPulls / $rareDropCount, 2);
        $averageMonthlyExpense = count($monthlyExpense) === 0 ? 0 : round(array_sum($monthlyExpense) / count($monthlyExpense), 0);

        $response = [
            'appId' => $appKey,
            'startDate' => Carbon::parse($statsStartDate)->format('Y-m-d'),
            'endDate' => Carbon::parse($statsEndDate)->format('Y-m-d'),
            'totalLogs' => count($logsAll),
            'monthsInPeriod' => $monthsInPeriod,
            'totalPulls' => $totalPulls,
            'rareDropCount' => $rareDropCount,
            'rareDropRate' => $rareDropRate,
            'totalExpense' => $totalExpense,
            'averageExpense' => $averageExpense,
            'averageMonthlyExpense' => $averageMonthlyExpense,
            'averageRareDropRate' => $averageRareDropRate,
        ];

        // 集計結果をキャッシュ
        StatsCache::updateOrCreate(
            ['cache_key' => $cacheKey],
            [
                'cache_key' => $cacheKey,
                'user_id' => $userId,
                'value'   => json_encode($response, JSON_UNESCAPED_UNICODE),
            ]
        );

        return response()->json($response);
    }

    private function getIntervalMonths(string $startDate, string $endDate): int
    {
        $date1 = Carbon::parse($startDate);
        $date2 = Carbon::parse($endDate);
        $interval = $date1->diff($date2);
        return ($interval->y * 12) + $interval->m + 1;
    }
}
