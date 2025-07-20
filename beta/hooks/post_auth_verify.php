<?php

// Hook for `POST /auth/verify`
if ($request_data['body']) {
    $response = null;
    $token = getRequestParam('token', $request_data['body']);
    $type = getRequestParam('type', $request_data['body']);
    //$token = $request_data['body']['token'] ?? null;
    //$type = $request_data['body']['type'] ?? null;
    if (!$token || !$type || !in_array($type, ['signup', 'reset'], true)) {
        returnError('Invalid Request.');// code: 400
    }
    $tokenDBFilePath = './responses/auth/token.json';
    $tokens1 = json_decode(file_get_contents($tokenDBFilePath));
    $tokens = initFileDBAsJSON('auth_tokens', '', false);
    $tokenMap = array_map(fn($t): string => $t['value'], $tokens);
    dump([$token, $type, $tokens1, $tokens, $tokenMap]);
    if (!in_array($token, $tokenMap, true)) {
        // 該当するトークンが存在しない場合は認証失敗
        returnError('Invalid Token.');// code: 401
    }
    $targetToken = array_filter($tokens, function($t) use ($token) {
        return $t['value'] === $token;
    });
    $tokenData = array_shift($targetToken);
    if ($tokenData['type'] !== $type) {
        // 認証要求とトークンのタイプが異なる場合
        returnError('Invalid Request.');// code: 400
    }
    $now = new DateTime();
    $tokenExpired = new DateTime($tokenData['expired']);
    if ($tokenExpired->format('Y-m-d H:i:s') < $now->format('Y-m-d H:i:s')) {
        // トークンの有効期限切れの場合
        returnError('Expired Token.');// code: 400
    }
    if ($tokenData['is_used']) {
        // トークン使用済みの場合
        returnError('Token Already Used.');// code: 400
    }

    //dump($tokenData);
    // トークン認証Ok
    if ($type === 'signup') {
        // 新規登録時のトークン認証の場合、該当ユーザーの認証済みフラグを変更
        $targetUserId = $tokenData['user_id'];
        $userDBFilePath = './responses/user/users.json';
        $users = initFileDBAsJSON('users', '', false);
        // ユーザーの引き当て処理や厳密なバリデーションは省略
        $now = new DateTime();
        $timezoneOffset = new DateTimeZone('UTC');
        $now->setTimezone($timezoneOffset);
        $nowISOString = $now->format("Y-m-d\TH:i:s\Z");
        foreach ($users as $i => $u) {
            if ($u['id'] === $targetUserId) {
                $u['is_verified'] = true;
                $u['updated_at'] = $nowISOString;
                $users[$i] = $u;
                break;
            }
        }
        file_put_contents($userDBFilePath, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // トークンを使用済みに更新
        foreach ($tokens as $i => $t) {
            if ($t['id'] === $tokenData['id']) {
                $t['is_used'] = true;
                $tokens[$i] = $t;
                break;
            }
        }
        file_put_contents($tokenDBFilePath, json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    returnResponse([
        'success' => true,
    ]);
}
