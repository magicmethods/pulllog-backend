<?php

$allowedHeaders = array_values(array_filter(array_map(
    static fn (string $header): string => trim($header),
    explode(',', (string) env('CORS_ALLOWED_HEADERS', 'Origin,X-Requested-With,Content-Type,Accept,x-api-key,x-csrf-token,x-upload-token'))
)));

if ($allowedHeaders !== ['*'] && !in_array('x-upload-token', $allowedHeaders, true)) {
    $allowedHeaders[] = 'x-upload-token';
}

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => explode(',',env('CORS_ALLOWED_METHODS', '*')),

    'allowed_origins' => explode(',',env('CORS_ALLOWED_ORIGINS', '*')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => $allowedHeaders,

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => filter_var(env('CORS_SUPPORTS_CREDENTIALS', true), FILTER_VALIDATE_BOOL),

];
