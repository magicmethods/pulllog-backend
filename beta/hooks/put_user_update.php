<?php

// Hook for `PUT /user/update`
// ~本来は `PUT /user/update` のエンドポイントだが、 PSR-7 サーバ非互換のため POST で受ける~
if ($request_data['body']) {
    //dump($request_data);
    $response = null;
    $userDBFilePath = './responses/user/users.json';
    $users = initFileDBAsJSON('users');
    $emailMap = array_map(fn($user): string => $user['email'], $users);
    if (!in_array($request_data['body']['email'], $emailMap, true)) {
        $response = [
            'state' => 'error',
            'message' => 'Invalid request.',
        ];
        header('Content-Type: application/json');
        //http_response_code(400);// Bad Request
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
    $now = new DateTime();
    $timezoneOffset = new DateTimeZone('UTC');
    $now->setTimezone($timezoneOffset);
    $nowISOString = $now->format("Y-m-d\TH:i:s\Z");
    
    // ユーザーデータの引き当て処理はモック用の簡易版
    $newUserData = null;
    foreach($users as $index => $user) {
        if ($user['email'] === $request_data['body']['email']) {
            // ユーザーデータの更新（暫定）
            $newUserData = $user;
            $newUserData['name'] = $request_data['body']['name'];
            if (!empty($request_data['body']['password'])) {
                $newUserData['password'] = password_hash($request_data['body']['password'], PASSWORD_DEFAULT);
            }
            if (isset($request_data['body']['avatarUrl'])) {
                $newUserData['avatar_url'] = $request_data['body']['avatarUrl'];
            }
            $newUserData['language'] = $request_data['body']['language'];
            $newUserData['theme'] = $request_data['body']['theme'];
            $newUserData['home_page'] = $request_data['body']['homePage'];
            $newUserData['updated_at'] = $nowISOString;

            $users[$index] = $newUserData;
            break;
        }
    }
    // DBを更新
    if ($newUserData) {
        file_put_contents($userDBFilePath, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        //dump($newUserData);
        // レスポンス用のユーザーデータからパスワードを削除
        unset($newUserData->password);
        $response = [
            'state' => 'success',
            'user' => $newUserData,
        ];
    } else {
        $response = [
            'state' => 'error',
            'message' => 'Target user not found.',
            'user' => null,
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}
