<?php

// Hook for `POST /user/avatar`
if ($request_data['files']) {
    $request_data['headers'] = getallheaders();
    $response = null;
    $userDBFilePath = './responses/user/users.json';
    $users = json_decode(file_get_contents($userDBFilePath));
    $emailMap = array_map(fn($user): string => $user->email, $users);
    
    $newAvatarName = '';
    if (preg_match('/^\[([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\]\.(.*)$/', rawurldecode($request_data['files']['avatar']['name']), $matches)) {
        $email = $matches[1];
        foreach($users as $user) {
            if ($user->email === $email) {
                $newAvatarName = 'avatar_'. $user->id .'.'. $matches[2];
                break;
            }
        }
    }
    $request_data['newAvatar'] = $newAvatarName;
    //@error_log(json_encode($request_data, JSON_PRETTY_PRINT) . "\n", 3, './logs/dump.log');
    
    $now = new DateTime();
    $timezoneOffset = new DateTimeZone('UTC');
    $now->setTimezone($timezoneOffset);
    $nowISOString = $now->format("Y-m-d\TH:i:s\Z");
    $nowTimestamp = $now->format('U');
    
    // ユーザーデータの引き当て処理はモック用の簡易版
    if (isset($request_data['files']['avatar']) && $request_data['files']['avatar']['size'] > 0) {
        // アバター画像がアップロードされている場合
        $avatarData = $request_data['files']['avatar'];
        if ($avatarData['error'] !== 0 || !is_uploaded_file($avatarData['tmp_name'])) {
            $response = [
                'state' => 'error',
                'message' => 'アバター画像のアップロードに失敗しました。',
                'user' => null,
            ];
            header('Content-Type: application/json');
            echo json_encode($response, JSON_PRETTY_PRINT);
            exit;
        }
        $newFileName = 'avatar_3.'. substr($avatarData['name'], strrpos($avatarData['name'], '.') + 1);
        $uploadDir = './temp/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $destPath = $uploadDir . $newAvatarName;
        if (move_uploaded_file($avatarData['tmp_name'], $destPath)) {
            $response = [
                'state' => 'success',
                'user' => [
                    'avatarUrl' => 'http://localhost:3030/temp/uploads/' . $newAvatarName .'?'. $nowTimestamp,
                ],
            ];
        } else {
            $response = [
                'state' => 'error',
                'message' => 'アバター画像の保存に失敗しました。',
                'user' => null,
            ];
            header('Content-Type: application/json');
            echo json_encode($response, JSON_PRETTY_PRINT);
            exit;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}
