<?php

// Hook for `POST /auth/password`
if ($request_data['body']) {
    $response = null;
    $email = $request_data['body']['email'] ?? null;
    if (!$email) {
        returnError('Invalid request.', 400);
    }
    //@error_log(json_encode($request_data, JSON_PRETTY_PRINT), 3, './logs/dump.log');
    
    $userDBFilePath = './responses/user/users.json';
    $users = json_decode(file_get_contents($userDBFilePath));
    $emailMap = array_map(fn($user): string => $user->email, $users);
    if (!in_array($email, $emailMap, true)) {
        // 該当するアカウントは存在しないが、登録メールアドレスの存在を通知しないために成功を返す
        returnResponse([
            'success' => true,
        ]);
    }
    $targetUser = array_filter($users, function($user) use ($email) {
        return $user->email === $email;
    });
    //@error_log(json_encode($targetUser, JSON_PRETTY_PRINT), 3, './logs/dump.log');
    $userData = array_shift($targetUser);
    if ($userData->is_deleted || !$userData->is_verified) {
        // 該当アカウントが削除済みか未承認である場合は、メール送信処理は行わず、成功を返す
        returnResponse([
            'success' => true,
        ]);
    }
    
    // パスワード再設定用のトークンを発行
    // 正式実装時、同一user_id&typeのトークンがあれば上書きし、なければ新規発行する（モックでは新規発行のみ）
    $tokenDBFilePath = './responses/auth/token.json';
    $tokens = json_decode(file_get_contents($tokenDBFilePath));
    $tokenIdMap = array_map(fn($t): string => $t->id, $tokens);
    $now = new DateTime();
    $timezoneOffset = new DateTimeZone('UTC');
    $now->setTimezone($timezoneOffset);
    $nowISOString = $now->format("Y-m-d\TH:i:s\Z");
    $expireDateTime = new DateTime(date('c', strtotime($nowISOString . '+1 day')));
    $expireISOString = $expireDateTime->format("Y-m-d\TH:i:s\Z");
    $newTokenData = [
        'id' => max($tokenIdMap) + 1,
        'user_id' => $userData->id,
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

// ---------- Utility functions ----------

function returnResponse(array $response, int $code = 200): void {
    header('Content-Type: application/json');
    if ($code !== 200) {
        http_response_code($code);
    }
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}
function returnError(string $message, int $code = 200): void {
    returnResponse([
        'success' => false,
        'message' => $message,
    ], $code);
}
