<?php
global $_SESSION;

// Hook for `GET /apps`
$current_user = $_SESSION['current_user'];
if ($current_user) {
    $userAppsDBFile = './responses/apps/userApps.json';
    $userAppsMap = json_decode(file_get_contents($userAppsDBFile));
    $userApps = array_find($userAppsMap, function($item) use($current_user) {
        return (int)$item->user_id === (int)$current_user['user_id'];
    });
    if (empty($userApps)) {
        $userAppsMap[] = [ 'user_id' => $current_user['user_id'], 'own_apps' => [] ];
        file_put_contents($userAppsDBFile, json_encode($userAppsMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    if (!empty($userApps->own_apps) && count($userApps->own_apps) > 0) {
        $appDBFile = './responses/apps/get/appData.json';
        $appList = json_decode(file_get_contents($appDBFile));
        $userAppList = [];
        foreach ($userApps->own_apps as $app_id) {
            $match_app = array_find($appList, function($app) use($app_id) {
                return $app->appId === $app_id;
            });
            if ($match_app) {
                $userAppList[] = $match_app;
            }
        }
        //dump($userAppList);
        returnResponse($userAppList);
    }

    returnResponse([]);
}
