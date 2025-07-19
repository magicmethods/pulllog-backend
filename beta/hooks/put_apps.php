<?php

// Hook for dynamic parameters in `PUT /apps/:id`
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
        // 指定 :id のアプリデータがあれば更新
        $applist[$keyIndex] = $request_data['body'];
        file_put_contents(
            $appDBFile,
            json_encode($applist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
        $response = $applist[$keyIndex];
    } else {
        // 指定 :id のアプリデータがなければエラーレスポンス
        $response = [ 'error' => 'Invalid API call.' ];
    }
    //dump([$appId, $applist, $keyIndex, $response]);

    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}