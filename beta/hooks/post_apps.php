<?php

// Hook for POST /apps
$current_user = $_SESSION['current_user'];
if ($request_data['body'] && $current_user) {
    $appDBFile = './responses/apps/get/appData.json';
    //$nowApplist = json_decode(file_get_contents($appDBFile));
    $nowApplist = initFileDBAsJSON('apps', '', false);
    $newAppData = $request_data['body'];
    $newAppId = uniqid();
    $newAppData['appId'] = $newAppId;
    $nowApplist[] = $newAppData;
    file_put_contents(
        $appDBFile,
        json_encode($nowApplist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
    $userAppsDBFile = './responses/apps/userApps.json';
    //$userAppsMap = json_decode(file_get_contents($userAppsDBFile));
    $userAppsMap = initFileDBAsJSON('user_apps', '', false);
    $userApps = array_find($userAppsMap, function($item) use($current_user) {
        return (int)$item['user_id'] === (int)$current_user['user_id'];
    });
    if (empty($userApps)) {
        // アプリリストが無ければ新規追加
        $userAppsMap[] = [ 'user_id' => $current_user['user_id'], 'own_apps' => [ $newAppId ] ];
    } else {
        // アプリリストがあれば更新
        foreach($userAppsMap as $i => $item) {
            if ((int)$item['user_id'] === (int)$current_user['user_id']) {
                $item['own_apps'][] = $newAppId;
                $userAppsMap[$i] = $item;
                break;
            }
        }
    }
    file_put_contents($userAppsDBFile, json_encode($userAppsMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    header('Content-Type: application/json');
    echo json_encode($newAppData, JSON_PRETTY_PRINT);
    exit;
}
