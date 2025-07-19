<?php

// Hook for dynamic parameters in `GET /logs/:id`
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
    $fromDate = isset($from) && isYmd($from) ? $from : null;
    $toDate = isset($to) && isYmd($to) ? $to : null;
    $limitNum = isset($limit) && (int)$limit > 0 ? (int)$limit : -1;
    $offsetNum = isset($offset) && (int)$offset > 0 ? (int)$offset : -1;
    $params = [ 'appId' => $appId, 'from' => $fromDate, 'to' => $toDate, 'limit' => $limitNum, 'offset' => $offsetNum ];
    //dump($params);

    $response = null;
    $appLogsFile = './responses/logs/'. $appId .'.json';
    if (file_exists($appLogsFile)) {
        $appLogs = json_decode(file_get_contents($appLogsFile));
        $pick_logs = [];
        $min_time = $fromDate ? strtotime($fromDate) : 0;
        $max_time = $toDate ? strtotime($toDate) : PHP_INT_MAX;
        $date_index = [];
        foreach($appLogs as $_log) {
            $log_time = strtotime($_log->date);
            if ($min_time <= $log_time && $log_time <= $max_time) {
                $pick_logs[] = $_log;
                $date_index[] = $log_time;
            }
        }
        array_multisort($date_index, SORT_ASC, SORT_NUMERIC, $pick_logs);
        if ($limitNum > 0 && $limitNum < count($pick_logs)) {
            $response = array_slice($pick_logs, $limitNum * -1);
        } else {
            $response = $pick_logs;
        }
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
