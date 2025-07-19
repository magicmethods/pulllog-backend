<?php

if (!function_exists('getEnv')) {
    /**
     * Get an environment variable with a fallback
     * @param string $key The environment variable key
     * @param mixed $default The default value if the key is not set
     * @return mixed The value of the environment variable or the default
     * @usage:
     * ```
     * $apiKey = getEnv('API_KEY', 'default_value');
     * ```
     */
    function getEnv(string $key, mixed $default = null): mixed {
        return $_ENV[$key] ?? $default;
    }
}

if (!function_exists('getRequestParam')) {
    /**
     * Get a parameter from the request body
     * @param string $keyName The key to look for in the request body
     * @param array $requestBody The request body, typically from json_decode
     * @return mixed The value of the parameter or false if not found
     * @usage:
     * ```
     * $param = getRequestParam('email', $requestBody);
     * ```
     */
    function getRequestParam(string $keyName, array $requestBody = []): mixed {
        if (!isset($requestBody) || empty($requestBody) || !array_key_exists($keyName, $requestBody)) {
            return false;
        }
        return $requestBody[$keyName];
    }
}

if (!function_exists('extractRouteParams')) {
    /**
     * Extract route parameters from a request path based on a route pattern
     * @param string $routePattern The route pattern with placeholders
     * @param string $requestPath The actual request path
     * @return array An associative array of extracted parameters
     * @usage:
     * ```
     * $params = extractRouteParams('/foo/:id/bar/:name', '/foo/123/bar/abc');
     * // result: ['id' => '123', 'name' => 'abc']
     * ```
     */
    function extractRouteParams(string $routePattern, string $requestPath): array {
        // :param名 を (?P<param名>[^/]+) へ置換
        $regex = preg_replace('#:([\w]+)#', '(?P<$1>[^/]+)', $routePattern);
        $regex = '#^' . $regex . '$#';
        if (preg_match($regex, $requestPath, $matches)) {
            // 連想配列だけ抜き出し
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }
        return [];
    }
}

if (!function_exists('returnResponse')) {
    /**
     * Generate a JSON response and exit
     * @param array $response The response data to return
     * @param int $code The HTTP status code to return (default is 200)
     * @usage:
     * ```
     * returnResponse(['success' => true, 'data' => $data]);
     * // returnResponse(['error' => 'Not found'], 404);
     * ```
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
     * Generate an error response and exit
     * @param string $message The error message to return
     * @param int $code The HTTP status code to return (default is 200)
     * @usage:
     * ```
     * returnError('Invalid request', 400);
     * ```
     */
    function returnError(string $message, int $code = 200): void {
        returnResponse([
            'success' => false,
            'message' => $message,
        ], $code);
    }
}

if (!function_exists('dump')) {
    /**
     * Dump a variable's contents
     * - Wrapper of `error_log(json_encode($var, JSON_PRETTY_PRINT), 3, './logs/dump.log')`
     * @param mixed $var The variable to dump
     * @param bool $toFile Whether to log to a file (default is true)
     * @param string $logFile The file to log the output (default is 'dump.log')
     * @return void
     * @usage:
     * ```
     * dump($someVariable);
     * ```
     */
    function dump(mixed $var, bool $toFile = true, string $logFile = 'dump.log'): void {
        if (!is_string($logFile) || empty($logFile)) {
            $logFile = 'dump.log';
        }
        $timestamp = date('Y-m-d H:i:s');
        if (is_array($var) || is_object($var)) {
            $output = json_encode($var, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $output = print_r($var, true);
        }
        $output = "[$timestamp] $output";
        if ($toFile) {
            $logFile = __DIR__ . '/../logs/' . $logFile;
            if (!is_dir(dirname($logFile))) {
                mkdir(dirname($logFile), 0755, true);
            }
            error_log($output, 3, $logFile);
        } else {
            // Output to standard output
            // note: This will not work in a web context, only in CLI
            header('Content-Type: text/plain');
            echo '<pre>' . $output . '</pre>';
        }
    }
}

if (!function_exists('initFileDBAsJSON')) {
    /**
     * Initialize the JSON file-based database then if an entity is specified 
     * it will retrieve content of that database.
     * @param string $entity The entity to retrieve (optional)
     * @param string $placeholder The placeholder for dynamic paths (optional)
     * @param bool $withCleanupSession Whether to clean up session files (default is false)
     * @return mixed
     * @usage:
     * ```
     * initFileDBAsJSON();
     * // Initialize and get users
     * $users = initFileDBAsJSON('users');
     * // Initialize and get logs for a specific user
     * $logs = initFileDBAsJSON('logs', 'appId001');
     * ```
     */
    function initFileDBAsJSON(
        string $entity = '',
        string $placeholder = '',
        bool $withCleanupSession = true
    ): mixed {
        $BASE_PATH = __DIR__ . '/..';
        $dbFilePaths = [
            'users'        => '/responses/user/users.json',
            'auth_tokens'  => '/responses/auth/token.json',
            'apps'         => '/responses/apps/get/appData.json',
            'user_apps'    => '/responses/apps/userApps.json',
            'logs'         => '/responses/logs/%s.json',
            'user_session' => '/sessions/%s.json',
        ];
        foreach ($dbFilePaths as $entity => $path) {
            if (in_array($entity, ['logs', 'user_session'], true)) {
                continue;
            }
            $filePath = $BASE_PATH . $path;
            if (!file_exists($filePath)) {
                // Ensure the directory exists
                $dir = dirname($filePath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                // Create the file if it does not exist
                file_put_contents($filePath, json_encode([]));
            }
            // Initialize the file with an empty array if it's empty
            if (filesize($filePath) === 0) {
                file_put_contents($filePath, json_encode([]));
            }
        }
        if ($withCleanupSession) {
            // Clean up session files
            $sessionDir = $BASE_PATH . '/sessions';
            if (is_dir($sessionDir)) {
                $files = glob($sessionDir . '/*.json');
                foreach ($files as $file) {
                    if (filemtime($file) < time() - 86400) { // 1 day in seconds
                        unlink($file);
                    }
                }
            }
        }
        if ($entity && array_key_exists($entity, $dbFilePaths)) {
            $isReturnEmptyArray = true;
            if ($placeholder && in_array($entity, ['logs', 'user_session'], true)) {
                $filePath = $BASE_PATH . sprintf($dbFilePaths[$entity], $placeholder);
                $isReturnEmptyArray = false; // Do not return empty array for logs and user_session
            } else {
                $filePath = $BASE_PATH . $dbFilePaths[$entity];
            }
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                return json_decode($content, true);
            }
            // fallback
            return $isReturnEmptyArray ? [] : null;
        } else {
            return null; // No specific entity requested
        }
    }
}
