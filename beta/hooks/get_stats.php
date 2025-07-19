<?php

// Hook for dynamic parameters in `GET /stats/:id`
$pattern = '/^dynamicParam\d+$/';
$matchingKeys = preg_grep($pattern, array_keys($request_data));
if (!empty($matchingKeys)) {
    // Extract dynamic parameters
    $filteredArray = array_filter($request_data, function ($key) use ($pattern) {
        return preg_match($pattern, $key);
    }, ARRAY_FILTER_USE_KEY);
    extract($filteredArray);
    $appId = isset($dynamicParam1) ? $dynamicParam1 : null;
    extract($request_data['query_params']);
    $startDate = isset($start) && isYmd($start) ? $start : null;
    $endDate = isset($end) && isYmd($end) ? $end : null;
    $params = [ 'appId' => $appId, 'start' => $startDate, 'end' => $endDate ];
    //dump($params);

    $response = null;
    $appLogsFile = './responses/logs/'. $appId .'.json';
    if (file_exists($appLogsFile)) {
        $appLogs = json_decode(file_get_contents($appLogsFile));
        $pick_logs = [];
        $min_time = $startDate ? strtotime($startDate) : 0;
        $max_time = $endDate ? strtotime($endDate) : PHP_INT_MAX;
        // Calc stats from logs
        $totalPulls = 0;
        $rareDropCount = 0;
        $dropDetails = [];
        $rareDropRate = 0;
        $totalExpense = 0;
        $tags = [];
        $averageExpense = 0;
        $averageRareDropRate = 0;
        $monthlyExpense = [];
        foreach($appLogs as $_log) {
            $log_time = strtotime($_log->date);
            if ($min_time <= $log_time && $log_time <= $max_time) {
                $pick_logs[] = $_log;
                $totalPulls += $_log->total_pulls;
                $rareDropCount += $_log->discharge_items;
                $dropDetails = array_merge($dropDetails, $_log->drop_details);
                $totalExpense += $_log->expense;
                $tags = array_merge($tags, $_log->tags);
                $date = new DateTime($_log->date);
                $year_month = $date->format('Y-m');
                if (array_key_exists($year_month, $monthlyExpense)) {
                    $monthlyExpense[$year_month] += $_log->expense;
                } else {
                    $monthlyExpense[$year_month] = $_log->expense;
                }
            }
        }
        $statsStartDate = $pick_logs[0]->date;
        $statsEndDate = end($pick_logs)->date;
        $monthsInPeriod = getIntervalMonths($statsStartDate, $statsEndDate);
        $rareDropRate = $rareDropCount / $totalPulls * 100;// unit '%'
        $averageExpense = $rareDropCount === 0 ? 0 : $totalExpense / $rareDropCount;
        $averageRareDropRate = $rareDropCount === 0 ? 0 : $totalPulls / $rareDropCount;
        $averageMonthlyExpense = array_sum(array_values($monthlyExpense)) / count($monthlyExpense);
        // sleep(1);
        $response = [
            'appId' => $appId,
            'startDate' => $statsStartDate,
            'endDate' => $statsEndDate,
            'totalLogs' => count($pick_logs),
            'monthsInPeriod' => $monthsInPeriod,
            'totalPulls' => $totalPulls,
            'rareDropCount' => $rareDropCount,
            'rareDropRate' => round($rareDropRate, 2),
            'totalExpense' => $totalExpense,
            'averageExpense' => round($averageExpense, 0),
            'averageMonthlyExpense' => round($averageMonthlyExpense, 0),
            'averageRareDropRate' => round($averageRareDropRate, 2),
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

function isYmd(string $dateStr): bool {
    if (empty($dateStr)) {
        return false;
    }
    if (!preg_match('/^(19|20)[0-9]{2}\-\d{2}\-\d{2}$/', $dateStr)) {
        return false;
    }
    list($y, $m, $d) = explode('-', $dateStr);
    return checkdate($m, $d, $y);
}

function getIntervalMonths(string $startDate, string $endDate): int {
    $date1 = new DateTime($startDate);
    $date2 = new DateTime($endDate);
    // 日付期間を取得（差分）
    $interval = $date1->diff($date2);
    // 年数と月数を計算
    $years = $interval->y;
    $months = $interval->m;
    // 全月数を計算（年数 * 12 + 月数 + 1）
    $totalMonths = ($years * 12) + $months + 1;
    return $totalMonths;
}