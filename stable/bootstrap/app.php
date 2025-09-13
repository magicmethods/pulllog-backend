<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use App\Http\Middleware\AuthApiKey;
use App\Http\Middleware\AuthCsrfToken;
use App\Http\Middleware\DemoGuard;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.apikey' => AuthApiKey::class, // APIキー認証ミドルウェア
            'auth.csrf' => AuthCsrfToken::class, // CSRFトークン認証ミドルウェア
            'demo.guard' => DemoGuard::class, // デモユーザー制御ミドルウェア
        ])->api(prepend: [ 'auth.apikey', 'demo.guard', 'auth.csrf' ]); // 認証ミドルウェアを先頭に追加
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
