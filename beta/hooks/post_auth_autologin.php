<?php

// Hook for `POST /auth/autologin`

$tokenDBFilePath = './responses/auth/token.json';
$userDBFilePath  = './responses/user/users.json';

// RememberトークンCookie取得
$cookieName = 'remember_token';
$rememberToken = $_COOKIE[$cookieName] ?? null;
//@error_log(json_encode($rememberToken, JSON_PRETTY_PRINT), 3, './logs/dump.log');

header('Content-Type: application/json');

if (!$rememberToken) {
    //http_response_code(401);
    echo json_encode(['state' => 'error', 'message' => 'No remember token.']);
    exit;
}

// トークンDBを読み込み
$tokens = file_exists($tokenDBFilePath) ? json_decode(file_get_contents($tokenDBFilePath)) : [];
$now = new DateTime('now', new DateTimeZone('UTC'));

// 有効なrememberトークンを検索
$tokenEntry = null;
foreach ($tokens as $i => $token) {
    if ($token->type === 'remember'
        && $token->value === $rememberToken
        && !$token->is_used
        && $now < new DateTime($token->expired)
    ) {
        $tokenEntry = $token;
        $tokenEntryIndex = $i;
        break;
    }
}

if (!$tokenEntry) {
    // トークンが無効・期限切れ・既に使用済み
    // Cookie削除指示
    setcookie($cookieName, '', time() - 3600, '/', '', false, true);
    //http_response_code(401);
    echo json_encode(['state' => 'error', 'message' => 'Invalid or expired token.']);
    exit;
}

// ユーザーデータ取得
$users = json_decode(file_get_contents($userDBFilePath));
$userData = null;
foreach ($users as $user) {
    if ($user->id == $tokenEntry->user_id) {
        $userData = $user;
        break;
    }
}
if (!$userData || $userData->is_deleted || !$userData->is_verified) {
    //http_response_code(401);
    echo json_encode(['state' => 'error', 'message' => 'User not found or invalid.']);
    exit;
}

// トークンを「使用済み」にして新規トークンを発行（ローテーション）
$tokens[$tokenEntryIndex]->is_used = true;

// 新しいrememberトークンを発行
$newRememberToken = bin2hex(random_bytes(40));
$newExpired = (clone $now)->add(new DateInterval('P30D'))->format('c');
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$newTokenEntry = (object)[
    'id'      => time() . rand(1000, 9999),
    'user_id' => $userData->id,
    'value'   => $newRememberToken,
    'type'    => 'remember',
    'code'    => null,
    'expired' => $newExpired,
    'is_used' => false,
    'ua'      => $ua,
    'ip'      => $ip
];
$tokens[] = $newTokenEntry;
file_put_contents($tokenDBFilePath, json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// 新しいCookieをセット
$cookieParams = [
    'expires=' . gmdate('D, d M Y H:i:s \G\M\T', strtotime($newExpired)),
    'path=/',
    'samesite=Lax',
    'httponly',
    'secure', // 本番環境は必ず有効化
];
header('Set-Cookie: remember_token=' . $newRememberToken . '; ' . implode('; ', $cookieParams), false);

// 疑似セッション発行
$sessionToken = bin2hex(random_bytes(32));
$sessionData = [
    'user_id' => $userData->id,
    'email' => $userData->email,
    'created_at' => gmdate('c'),
    'expires_at' => gmdate('c', time() + 1 * 3600) // 1時間有効
];
file_put_contents(__DIR__ . "/../sessions/{$sessionToken}.json", json_encode($sessionData, JSON_PRETTY_PRINT));

// レスポンス
echo json_encode([
    'state' => 'success',
    'user' => $userData,
    'csrfToken' => $sessionToken,
    'remember_token' => $newRememberToken,// 開発時のCORS環境用
]);
exit;