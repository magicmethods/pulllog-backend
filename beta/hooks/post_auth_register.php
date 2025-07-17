<?php

// Hook for `POST /auth/register`
if ($request_data['body']) {
    $response = null;
    $userDBFilePath = './responses/user/users.json';
    $users = json_decode(file_get_contents($userDBFilePath));
    $emailMap = array_map(fn($user): string => $user->email, $users);
    if (in_array($request_data['body']['email'], $emailMap, true)) {
        $response = [
            'state' => 'error',
            'message' => 'このメールアドレスは既に登録済みです。ログインを試すか、パスワードを再設定してください。',
        ];
        header('Content-Type: application/json');
        //http_response_code(400);// Bad Request
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
    $userIdMap = array_map(fn($user): int => $user->id, $users);
    $newUserId = count($userIdMap) === 0 ? 1 : max($userIdMap) + 1;
    $now = new DateTime();
    $timezoneOffset = new DateTimeZone('UTC');
    $now->setTimezone($timezoneOffset);
    $nowISOString = $now->format("Y-m-d\TH:i:s\Z");
    // 登録用アカウントデータ
    $user_data = [
        'id' => $newUserId,
        'name' => $request_data['body']['name'],
        'email' => $request_data['body']['email'],
        'password' => password_hash($request_data['body']['password'], PASSWORD_DEFAULT),
        'avatar_url' => null,
        'roles' => 'user',
        'plan' => 'free',
        'plan_expiration' => '2026-06-30',
        'language' => $request_data['body']['language'] ?? 'ja',
        'theme' => 'light',
        'home_page' => '/apps',
        'created_at' => $nowISOString,
        'updated_at' => $nowISOString,
        'last_login' => '',
        'last_login_ip' => '',
        'last_login_user_agent' => '',
        'is_deleted' => false,
        'is_verified' => false,
        'unread_notifications' => [],
    ];
    // 新規ユーザを追加
    $users[] = $user_data;
    file_put_contents($userDBFilePath, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    //@error_log(json_encode($user_data, JSON_PRETTY_PRINT), 3, './logs/dump.log');
    // レスポンスデータにはユーザーデータを含まない
    // レスポンス用のユーザーデータからパスワードを削除
    //unset($user_data['password']);
    // 新規登録ではCSRFトークンは発行しない
    //$csrf_token = '';// 'DebugCSRFToken001';
    $response = [
        'state' => 'success',
    ];
    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}
