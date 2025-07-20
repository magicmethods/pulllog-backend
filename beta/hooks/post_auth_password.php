<?php

// Hook for `POST /auth/password`
if ($request_data['body']) {
    $response = null;
    $email = $request_data['body']['email'] ?? null;
    if (!$email) {
        returnError('Invalid request.', 400);
    }
    //dump($request_data);

    //$userDBFilePath = './responses/user/users.json';
    $users = initFileDBAsJSON('users');
    $emailMap = array_map(fn($user): string => $user['email'], $users);
    if (!in_array($email, $emailMap, true)) {
        // 該当するアカウントは存在しないが、登録メールアドレスの存在を通知しないために成功を返す
        returnResponse([
            'success' => true,
        ]);
    }
    $targetUser = array_filter($users, function($user) use ($email) {
        return $user['email'] === $email;
    });
    //dump($targetUser);
    $userData = array_shift($targetUser);
    if ($userData['is_deleted'] || !$userData['is_verified']) {
        // 該当アカウントが削除済みか未承認である場合は、メール送信処理は行わず、成功を返す
        returnResponse([
            'success' => true,
        ]);
    }
    
    // パスワード再設定用のトークンを発行
    // 正式実装時、同一user_id&typeのトークンがあれば上書きし、なければ新規発行する（モックでは新規発行のみ）
    $tokenDBFilePath = './responses/auth/token.json';
    $tokens = initFileDBAsJSON('auth_tokens', '', false);
    $tokenIdMap = array_map(fn($t): string => $t['id'], $tokens);
    $now = new DateTime();
    $timezoneOffset = new DateTimeZone('UTC');
    $now->setTimezone($timezoneOffset);
    $nowISOString = $now->format("Y-m-d\TH:i:s\Z");
    $expireDateTime = new DateTime(date('c', strtotime($nowISOString . '+1 day')));
    $expireISOString = $expireDateTime->format("Y-m-d\TH:i:s\Z");
    $newTokenData = [
        'id' => !empty($tokenIdMap) ? max($tokenIdMap) + 1 : 1,
        'user_id' => $userData['id'],
        'value' => bin2hex(random_bytes(32)), // token
        'type' => 'reset',
        'code' => strtoupper(substr(uniqid(), 0, 6)),
        'expired' => $expireISOString,
        'is_used' => false,
    ];
    $tokens[] = $newTokenData;
    file_put_contents($tokenDBFilePath, json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // パスワードリマインドメールの送信処理（モックでは未実装）
    
    // 正常処理完了
    returnResponse([
        'success' => true,
    ]);
}
