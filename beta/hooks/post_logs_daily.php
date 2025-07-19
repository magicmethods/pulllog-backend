<?php

// Hook for dynamic parameters in `POST /logs/daily/:id/:date`
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
        // アプリログファイルがあればファイルを開く
        $appLogs = json_decode(file_get_contents($appLogsFile));
        $keyIndex = array_search($date, array_column($appLogs, 'date'), true);
        if ($keyIndex === false) {
            // 指定 :date のログデータがなければ新規追加（正常系）
            $appLogs[] = $request_data['body'];
        } else {
            // 指定 :date のログデータがあれば上書き（準正常系）
            $appLogs[$keyIndex] = $request_data['body'];
        }
    } else {
        // アプリログファイルがなければあらたに作成する
        $appLogs = [];
        $appLogs[] = $request_data['body'];
    }
    // アプリログファイルの並び替え（日次順に昇順ソート）
    array_multisort(array_column($appLogs, 'date'), SORT_ASC, $appLogs);
    // アプリログファイルを更新
    file_put_contents(
        './responses/logs/'. $appId .'.json',
        json_encode($appLogs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
    // 更新データをレスポンスとして返す
    $response = $request_data['body'];
    //dump([$appId, $date, $response, count($appLogs)]);

    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}