<?php

// Hook for dynamic parameters in `GET /logs/daily/:id/:date`
$pattern = '/^dynamicParam\d+$/';
$matchingKeys = preg_grep($pattern, array_keys($request_data));
if (!empty($matchingKeys)) {
    // Extract dynamic parameters
    $filteredArray = array_filter($request_data, function ($key) use ($pattern) {
        return preg_match($pattern, $key);
    }, ARRAY_FILTER_USE_KEY);
    extract($filteredArray);
    $appId = isset($dynamicParam2) ? $dynamicParam2 : null;
    $date = isset($dynamicParam3) ? $dynamicParam3 : null;
    $appLogsFile = './responses/logs/'. $appId .'.json';
    if (file_exists($appLogsFile)) {
        $appLogs = json_decode(file_get_contents($appLogsFile));
        $keyIndex = array_search($date, array_column($appLogs, 'date'), true);
        if ($keyIndex !== false) {
            // 指定 :date のログデータがあれば
            $response = $appLogs[$keyIndex];
        } else {
            // 指定 :date のログデータがなければ null
            $response = null;
        }
    } else {
        $response = null;
    }
    //@error_log(json_encode([$appId, $date, $response], JSON_PRETTY_PRINT), 3, './logs/dump.log');

    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}