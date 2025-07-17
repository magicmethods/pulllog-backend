<?php

// Hook for `POST /auth/login`
if ($request_data['body']) {
    $response = null;
    $email = $request_data['body']['email'];
    $password = $request_data['body']['password'];
    $userDBFilePath = './responses/user/users.json';
    $tokenDBFilePath = './responses/auth/token.json';
    $users = json_decode(file_get_contents($userDBFilePath));
    $tokens = $tokens = file_exists($tokenDBFilePath)
        ? json_decode(file_get_contents($tokenDBFilePath))
        : [];
    
    // ユーザー認証ロジック
    $emailMap = array_map(fn($user): string => $user->email, $users);
    if (!in_array($email, $emailMap, true)) {
        returnResponse([
            'state' => 'error',
            'message' => 'There is no matching account. Please register a new account.',
            'user' => null,
            'csrfToken' => null,
        ]);// or 400 Bad Request
    }
    $targetUser = array_filter($users, function($user) use ($email, $password) {
        return $user->email === $email && password_verify($password, $user->password);
    });
    //@error_log(json_encode($targetUser, JSON_PRETTY_PRINT), 3, './logs/dump.log');
    if (empty($targetUser) || count($targetUser) !== 1) {
        returnResponse([
            'state' => 'error',
            'message' => 'The data entered is incorrect.',
            'user' => null,
            'csrfToken' => null,
        ]);// or 400 Bad Request
    }
    $userData = array_shift($targetUser);
    if ($userData->is_deleted || !$userData->is_verified) {
        returnResponse([
            'state' => 'error',
            'message' => 'You cannot log in because the account you specified is invalid.',
            'user' => null,
            'csrfToken' => null,
        ]);// or 401 Unauthorized
    }
    
    // ログイン情報の更新
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $userData->last_login = $now->format("Y-m-d\TH:i:s\Z");
    $userData->last_login_ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userData->last_login_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $userData->unread_notifications = [];
    
    // 配列の該当ユーザー更新
    $indexKey = array_find_key($users, function($user) use ($userData) { return $user->id === $userData->id; });
    $users[$indexKey] = $userData;
    //@error_log(json_encode($userData, JSON_PRETTY_PRINT), 3, './logs/dump.log');
    file_put_contents($userDBFilePath, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // 疑似セッションのセット
    $sessionToken = bin2hex(random_bytes(32));
    $sessionData = [
        'user_id' => $userData->id,
        'email' => $userData->email,
        'created_at' => gmdate('c'),
        'expires_at' => gmdate('c', time() + 1 * 3600) // 1時間有効
    ];
    file_put_contents(__DIR__ . "/../sessions/{$sessionToken}.json", json_encode($sessionData, JSON_PRETTY_PRINT));

    // Rememberトークン処理
    $rememberToken = null;
    $rememberTokenCookie = null;
    $remember = getRequestParam('remember', $request_data['body']);
    if ($remember) {
        // 有効期限: 30日後
        $expireSec = 30 * 24 * 3600;
        $expired = gmdate('c', time() + $expireSec);
        
        // トークン生成
        $rememberToken = bin2hex(random_bytes(40));
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // トークンDBに追加
        $tokenEntry = [
            'id'      => intval(time() . rand(1000, 9999)),
            'user_id' => $userData->id,
            'value'   => $rememberToken,
            'type'    => 'remember',
            'code'    => null,
            'expired' => $expired,
            'is_used' => false,
            'ua'      => $ua,
            'ip'      => $ip
        ];
        $tokens[] = (object)$tokenEntry;
        file_put_contents($tokenDBFilePath, json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Set-Cookie用文字列
        $cookieParams = [
            'expires=' . gmdate('D, d M Y H:i:s \G\M\T', time() + $expireSec),
            'path=/',
            'samesite=Lax', // SPAならLaxが無難。SameSite=None; Secureも可（本番は必ずSecure）
            'httponly',
            'secure', // 本番は有効化
        ];
        $rememberTokenCookie = "remember_token={$rememberToken}; " . implode('; ', $cookieParams);
        // CORS環境ではフロントエンドにCookieセットできない
        header("Set-Cookie: $rememberTokenCookie", false);
        //setcookie('remember_token', $rememberToken, time() + $expireSec, '/', 'pull.log', true, false);
    } else {
        // remember未チェック時は、既存のremember_tokenを無効化（削除）する
        $filteredTokens = array_filter($tokens, function($token) use ($userData) {
            return !($token->type === 'remember' && $token->user_id === $userData->id);
        });
        if (count($filteredTokens) !== count($tokens)) {
            file_put_contents($tokenDBFilePath, json_encode(array_values($filteredTokens), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        // クライアントのCookie削除（有効期限過去に）
        if (isset($_COOKIE['remember_token'])) {
            // CORS環境ではフロントエンドにCookieセットできない
            header("Set-Cookie: remember_token=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; samesite=Lax; httponly", false);
            //setcookie('remember_token', '', time() - $expireSec, '/', 'pull.log', true, false);
        }
    }

    // レスポンス
    returnResponse([
        'state' => 'success',
        'user' => $userData,
        'csrfToken' => $sessionToken,
        'rememberToken' => $rememberToken // 本番ではAPIレスポンスに含めなくてOKだが、CORSな開発環境では渡す必要がある
    ]);
}

// ----

if (!function_exists('getRequestParam')) {
    function getRequestParam(string $keyName, array $requestBody = []): mixed {
        if (!isset($requestBody) || empty($requestBody) || !array_key_exists($keyName, $requestBody)) {
            return false;
        }
        return $requestBody[$keyName];
    }
}

if (!function_exists('returnResponse')) {
    /**
     * Output the JSON response and exit
     */
    function returnResponse(array $response, int $code = 200): void {
        header('Content-Type: application/json');
        if ($code !== 200) {
            http_response_code($code);
        }
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
}

if (!function_exists('returnError')) {
    /**
     * Generate error data and respond
     */
    function returnError(string $message, int $code = 200): void {
        returnResponse([
            'success' => false,
            'message' => $message,
        ], $code);
    }
}
