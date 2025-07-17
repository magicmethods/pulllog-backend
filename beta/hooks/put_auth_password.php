<?php

// Hook for `PUT /auth/password`
if ($request_data['body']) {
    $response = null;
    $token = $request_data['body']['token'] ?? null;
    $type = $request_data['body']['type'] ?? null;
    $code = $request_data['body']['code'] ?? null;
    $password = $request_data['body']['password'] ?? null;
    if (!$token || !$type || $type !== 'reset' || !$code || !$password) {
        returnError('Invalid Request.');// code: 400
    }
    @error_log(json_encode($request_data, JSON_PRETTY_PRINT), 3, './logs/dump.log');
    
    $tokenDBFilePath = './responses/auth/token.json';
    $tokens = json_decode(file_get_contents($tokenDBFilePath));
    $tokenMap = array_map(fn($t): string => $t->value, $tokens);
    if (!in_array($token, $tokenMap, true)) {
        // 該当するトークンが存在しない場合は認証失敗
        returnError('Invalid Token.');// code: 401
    }
    $targetToken = array_filter($tokens, function($t) use ($token) {
        return $t->value === $token;
    });
    $tokenData = array_shift($targetToken);
    if ($tokenData->type !== $type) {
        // 認証要求とトークンのタイプが異なる場合
        returnError('Invalid Request.');// code: 400
    }
    $now = new DateTime();
    $tokenExpired = new DateTime($tokenData->expired);
    if ($tokenExpired->format('Y-m-d H:i:s') < $now->format('Y-m-d H:i:s')) {
        // トークンの有効期限切れの場合
        // ※ 直前に /auth/verify でOkのため、連続処理想定のここではチェックしない方が良いだろうか
        // 有効期限切れギリギリでパスワード再設定にアクセスした際、認証コードや新パスを入力中に時間切れになっても許容してあげる方がユーザビリティが良いような気がするが…
        returnError('Expired Token.');// code: 400
    }
    if ($tokenData->is_used) {
        // トークン使用済みの場合
        returnError('Token Already Used.');// code: 400
    }
    if ($tokenData->code !== $code) {
        // 認証コードが間違っている場合
        returnError('Invalid Code.');// code: 400
    }
    
    @error_log(json_encode($tokenData, JSON_PRETTY_PRINT), 3, './logs/dump.log');
    // トークン&認証コードOk
    // 該当ユーザーのパスワードを変更
    $targetUserId = $tokenData->user_id;
    $userDBFilePath = './responses/user/users.json';
    $users = json_decode(file_get_contents($userDBFilePath));
    // ユーザーの引き当て処理や厳密なバリデーションは省略
    $now = new DateTime();
    $timezoneOffset = new DateTimeZone('UTC');
    $now->setTimezone($timezoneOffset);
    $nowISOString = $now->format("Y-m-d\TH:i:s\Z");
    foreach ($users as $i => $u) {
        if ($u->id === $targetUserId) {
            $u->password = password_hash($password, PASSWORD_DEFAULT);
            $u->updated_at = $nowISOString;
            break;
        }
    }
    file_put_contents($userDBFilePath, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // トークンを使用済みに更新
    foreach ($tokens as $i => $t) {
        if ($t->id === $tokenData->id) {
            $t->is_used = true;
            $tokens[$i] = $t;
            break;
        }
    }
    file_put_contents($tokenDBFilePath, json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
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
