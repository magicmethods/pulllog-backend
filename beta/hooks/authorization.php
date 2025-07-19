<?php

// Hook for custom authorization

// 認可不要エンドポイント
$path_through_check = [
    $_ENV['BASE_PATH'] . '/auth/login',
    $_ENV['BASE_PATH'] . '/auth/autologin',
    $_ENV['BASE_PATH'] . '/auth/register',
    $_ENV['BASE_PATH'] . '/auth/password',
    $_ENV['BASE_PATH'] . '/auth/verify',
    $_ENV['BASE_PATH'] . '/version',
    '/favicon.ico',
];

// 必須APIキー
$required_api_key = $_ENV['API_KEY'] ?? null;

$auth_request = [
    'path' => $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'],
    'csrf_token' => $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_COOKIE['csrf_token'] ?? '',
    'api_key' => $_SERVER['HTTP_X_API_KEY'] ?? '',
];

//dump($auth_request);

// APIキーのチェック
if (!in_array($auth_request['path'], $path_through_check, true)) {
    if (empty($auth_request['api_key']) || $auth_request['api_key'] !== $required_api_key) {
        header('HTTP/1.0 401 Unauthorized');
        http_response_code(401);
        echo 'Unauthorized';
        exit;
    }
    
    // CSRFトークンのチェック（セッションと突合）
    // $valid_token = 'CSRFToken4Debug';// デバッグ用の固定値
    //dump($_SESSION);
    if (empty($auth_request['csrf_token'])) {
        header('HTTP/1.0 401 Unauthorized');
        exit('Unauthorized: No session token');
    }
    $sessionData = initFileDBAsJSON('user_session', $auth_request['csrf_token'], true);
    if (!$sessionData) {
        header('HTTP/1.0 401 Unauthorized');
        exit('Unauthorized: No session token');
    }
    // 有効期限チェック
    if (strtotime($sessionData['expires_at']) < time()) {
        header('HTTP/1.0 401 Unauthorized');
        exit('Unauthorized: Session expired');
    }
    
    // 必要に応じてグローバル変数か$_SESSIONにユーザーID等をセット
    // $GLOBALS['current_user'] = $sessionData;
    $_SESSION['current_user'] = $sessionData;
}
