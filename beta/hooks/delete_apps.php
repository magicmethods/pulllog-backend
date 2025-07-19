<?php

// Hook for dynamic parameters in `DELETE /apps/:id`
$pattern = '/^dynamicParam\d+$/';
$matchingKeys = preg_grep($pattern, array_keys($request_data));
if (!empty($matchingKeys)) {
    // Extract dynamic parameters
    $filteredArray = array_filter($request_data, function ($key) use ($pattern) {
        return preg_match($pattern, $key);
    }, ARRAY_FILTER_USE_KEY);
    extract($filteredArray);
    $appId = isset($dynamicParam1) ? $dynamicParam1 : null;

    $appDBFile = './responses/apps/get/appData.json';
    $applist = json_decode(file_get_contents($appDBFile));
    $keyIndex = array_search($appId, array_column($applist, 'appId'), true);
    if ($keyIndex !== false) {
        // 指定 :id のアプリデータがあれば削除
        unset($applist[$keyIndex]);
        file_put_contents(
            $appDBFile,
            json_encode($applist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
        $response = [ 'state' => 'success' ];
    } else {
        // 指定 :id のアプリデータがなければエラーレスポンス
        $response = [ 'state' => 'error', 'message' => 'Invalid API call.' ];
    }
    //dump([$appId, $applist, $keyIndex, $response]);

    // アプリリストからも削除
    $current_user = $_SESSION['current_user'];
    $userAppsDBFile = './responses/apps/userApps.json';
    $userAppsMap = json_decode(file_get_contents($userAppsDBFile));
    foreach($userAppsMap as $i => $item) {
        if (in_array($appId, $item->own_apps, true)) {
            $userAppsMap[$i]->own_apps = array_filter($item->own_apps, function($aid) use($appId) {
                return $aid !== $appId;
            });
        }
    }
    file_put_contents($userAppsDBFile, json_encode($userAppsMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}