<?php

/**
 * PullLog API
 * ガチャ履歴管理アプリ「PullLog」バックエンドAPI仕様
 * PHP version 8.3
 *
 * The version of the OpenAPI document: 1.0.0
 */

use App\Services\LocaleResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
//use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\PasswordController;
use App\Http\Controllers\Api\Auth\OauthController;
//use App\Http\Controllers\Api\DefaultController;
use App\Http\Controllers\Api\Apps\AppsController;
use App\Http\Controllers\Api\Logs\LogsController;
use App\Http\Controllers\Api\Logs\DailyLogController;
use App\Http\Controllers\Api\Logs\LogImportController;
use App\Http\Controllers\Api\Stats\StatsController;
use App\Http\Controllers\Api\User\ProfileController;

Route::prefix(config('api.base_uri', 'v1'))->group(function () {

    // ダミールート（APIの動作確認用）
    Route::get('/dummy', function (Request $request) {
        $cookie = $request->headers->get('cookie', '');
        $lang = LocaleResolver::resolve($request, null);
        return response()->json([
            'status' => 'success',
            'message' => trans('messages.api_running_successfully', [], $lang),
            'cookie' => $cookie,
            'timestamp' => now()->toIso8601String(),
        ]);
    })->withoutMiddleware(['auth.apikey', 'auth.csrf'])
      ->name('api.dummy');

    // ここから OpenAPI スキーマから生成したルート定義 `generated/routes.php` をマージ:

    // /auth/** 系（APIキー認証のみ）
    Route::prefix('auth')
        ->middleware(['auth.apikey']) // 独自APIキー認証ミドルウェア
        ->withoutMiddleware(['auth.csrf']) // CSRFトークン認証は不要
        ->group(function () {
            // 認証関連のルート
            Route::post('register',   [RegisterController::class, 'register'])->name('auth.register');
            Route::post('verify',     [RegisterController::class, 'verifyEmail'])->name('auth.verify');
            Route::post('login',      [LoginController::class, 'login'])->name('auth.login');
            Route::post('autologin',  [LoginController::class, 'autologin'])->name('auth.autologin');
            Route::post('logout',     [LoginController::class, 'logout'])->name('auth.logout');
            Route::post('password',   [PasswordController::class, 'requestReset'])->name('auth.password.request');
            Route::put( 'password',   [PasswordController::class, 'reset'])->name('auth.password.reset');
            Route::post('google/exchange', [OauthController::class, 'googleExchange'])->name('auth.google.exchange');
        });

    // その他（APIキー認証+CSRFトークン認証が必要）
    Route::middleware(['auth.apikey', 'auth.csrf'])
        ->group(function () {
            // アプリ関連のルート
            Route::prefix('apps')->group(function () {
                Route::get(   '/',        [AppsController::class, 'getAppList'])->name('apps.list');
                Route::post(  '/',        [AppsController::class, 'registerApp'])->name('apps.app.register');
                Route::get(   '/{appId}', [AppsController::class, 'getAppData'])->name('apps.app.get');
                Route::put(   '/{appId}', [AppsController::class, 'updateApp'])->name('apps.app.update');
                Route::delete('/{appId}', [AppsController::class, 'deleteApp'])->name('apps.app.delete');
            });

            // ログ関連のルート
            Route::prefix('logs')->group(function () {
                // アプリ全体ログ取得
                Route::get(   '/{app}',              [LogsController::class, 'index'])->name('logs.index');
                // 日別ログ取得・更新・削除
                Route::get(   '/daily/{app}/{date}', [DailyLogController::class, 'show'])->name('logs.daily.show');
                Route::post(  '/daily/{app}/{date}', [DailyLogController::class, 'insert'])->name('logs.daily.insert');
                Route::put(   '/daily/{app}/{date}', [DailyLogController::class, 'update'])->name('logs.daily.update');
                Route::delete('/daily/{app}/{date}', [DailyLogController::class, 'destroy'])->name('logs.daily.destroy');
                // ログインポート
                Route::post(  '/import/{app}',       [LogImportController::class, 'import'])->name('logs.import');
            });

            // 統計関連のルート
            Route::prefix('stats')->group(function () {
                Route::get(   '/{appId}', [StatsController::class, 'getAppStats'])->name('stats.app.get');
            });

            // ユーザー関連のルート
            Route::prefix('user')->group(function () {
                Route::get(   '/',        [ProfileController::class, 'userGet'])->name('user.get');
                Route::put(   '/update',  [ProfileController::class, 'update'])->name('user.update');
                Route::delete('/',        [ProfileController::class, 'delete'])->name('user.delete');
                Route::post(  '/avatar',  [ProfileController::class, 'avatar'])->name('user.avatar');
            });
        });

});
