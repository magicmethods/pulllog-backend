<?php

// Hook for `POST /auth/logout`
$current_user = $_SESSION['current_user'];
if ($current_user) {
    $tokenDBFilePath = './responses/auth/token.json';
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);

    // セッションファイルのパスは authorization.php のフックで定義される
    // $sessionFile = __DIR__ . "/../sessions/{$auth_request['csrf_token']}.json";
    if (file_exists($sessionFile)) {
        @unlink($sessionFile);
    }

    // トークンDBから「現在のremember_token」を失効させる
    $rememberToken = $_COOKIE['remember_token'] ?? null;
    if ($rememberToken) {
        $tokens = file_exists($tokenDBFilePath) ? json_decode(file_get_contents($tokenDBFilePath)) : [];
        $modified = false;
        foreach ($tokens as $token) {
            if ($token->type === 'remember' && $token->value === $rememberToken) {
                $token->is_used = true; // 失効
                $modified = true;
            }
        }
        if ($modified) {
            file_put_contents($tokenDBFilePath, json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
    //@error_log(json_encode([$auth_request, $sessionFile, $current_user], JSON_PRETTY_PRINT), 3, './logs/dump.log');
    //exit;

    // レスポンス
    $response = [ 'success' => true, 'message' => 'Logged out successfully.' ];
    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}
