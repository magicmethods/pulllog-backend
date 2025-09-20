<?php

use App\Services\LocaleResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
//use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\PasswordController;
use App\Http\Controllers\Api\Auth\OauthController;
use App\Http\Controllers\Api\Auth\LogoutController;
//use App\Http\Controllers\Api\DefaultController;
use App\Http\Controllers\Api\Apps\AppsController;
use App\Http\Controllers\Api\Logs\LogsController;
use App\Http\Controllers\Api\Logs\DailyLogController;
use App\Http\Controllers\Api\Logs\LogImportController;
use App\Http\Controllers\Api\Stats\StatsController;
use App\Http\Controllers\Api\User\ProfileController;
use App\Http\Controllers\Api\UserFilters\UserFilterController;
use App\Http\Controllers\Api\Currencies\CurrencyController;

Route::prefix(config('api.base_uri', 'v1'))->group(function () {

    // �_�~�[���[�g: API�̉ғ��m�F�p
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

    // �������� OpenAPI �X�L�[�}���琶���������[�g��` `generated/routes.php` ��ǂ�:

    // API�L�[�F�؂݂̂̃��[�g�i���O�A�E�g�ȊO�j /auth/** �n
    Route::prefix('auth')
        ->middleware(['auth.apikey'])
        ->withoutMiddleware(['auth.csrf'])
        ->group(function () {
            // �F�؊֘A�̃��[�g
            Route::post('register',   [RegisterController::class, 'register'])->name('auth.register');
            Route::post('verify',     [RegisterController::class, 'verifyEmail'])->name('auth.verify');
            Route::post('login',      [LoginController::class, 'login'])->name('auth.login');
            Route::post('autologin',  [LoginController::class, 'autologin'])->name('auth.autologin');
            Route::post('password',   [PasswordController::class, 'requestReset'])->name('auth.password.request');
            Route::put( 'password',   [PasswordController::class, 'reset'])->name('auth.password.reset');
            Route::post('csrf/refresh', [LoginController::class, 'refreshCsrf'])->name('auth.csrf.refresh');
            Route::post('google/exchange', [OauthController::class, 'googleExchange'])->name('auth.google.exchange');
        });

    // ����ȊO: API�L�[�F��+CSRF�g�[�N���F�؂��K�v
    Route::middleware(['auth.apikey', 'auth.csrf', 'demo.guard'])
        ->group(function () {
            // �A�v���֘A�̃��[�g
            Route::prefix('apps')->group(function () {
                Route::get(   '/',        [AppsController::class, 'getAppList'])->name('apps.list');
                Route::post(  '/',        [AppsController::class, 'registerApp'])->name('apps.app.register');
                Route::get(   '/{appId}', [AppsController::class, 'getAppData'])->name('apps.app.get');
                Route::put(   '/{appId}', [AppsController::class, 'updateApp'])->name('apps.app.update');
                Route::delete('/{appId}', [AppsController::class, 'deleteApp'])->name('apps.app.delete');
            });

            // ���O�֘A�̃��[�g
            Route::prefix('logs')->group(function () {
                // �A�v���S�̃��O�擾
                Route::get(   '/{app}',              [LogsController::class, 'index'])->name('logs.index');
                // �������O�擾�E�X�V�E�폜
                Route::get(   '/daily/{app}/{date}', [DailyLogController::class, 'show'])->name('logs.daily.show');
                Route::post(  '/daily/{app}/{date}', [DailyLogController::class, 'insert'])->name('logs.daily.insert');
                Route::put(   '/daily/{app}/{date}', [DailyLogController::class, 'update'])->name('logs.daily.update');
                Route::delete('/daily/{app}/{date}', [DailyLogController::class, 'destroy'])->name('logs.daily.destroy');
                // ���O�C���|�[�g
                Route::post(  '/import/{app}',       [LogImportController::class, 'import'])->name('logs.import');
            });

            // ���v�֘A�̃��[�g
            Route::prefix('stats')->group(function () {
                Route::get(   '/{appId}', [StatsController::class, 'getAppStats'])->name('stats.app.get');
            });

            // ���[�U�[�֘A�̃��[�g
            Route::prefix('user')->group(function () {
                Route::get(   '/',        [ProfileController::class, 'userGet'])->name('user.get');
                Route::put(   '/update',  [ProfileController::class, 'update'])->name('user.update');
                Route::delete('/',        [ProfileController::class, 'delete'])->name('user.delete');
                Route::post(  '/avatar',  [ProfileController::class, 'avatar'])->name('user.avatar');
            });

            // ���[�U�[�t�B���^�ݒ�
            Route::prefix('user-filters')->group(function () {
                Route::get(   '/{context}', [UserFilterController::class, 'show'])->name('user-filters.show');
                Route::put(   '/{context}', [UserFilterController::class, 'update'])->name('user-filters.update');
            });

            // ���O�A�E�g�p���[�g
            Route::prefix('auth')->group(function () {
                Route::post('logout', [LogoutController::class, 'logout'])->name('auth.logout');
            });
        });

    Route::prefix('currencies')
        ->middleware(['auth.apikey'])
        ->withoutMiddleware(['auth.csrf'])
        ->group(function () {
            Route::get('/', [CurrencyController::class, 'index'])->name('currencies.index');
            // �ʒʉݎ擾�͍���K�v�ɂȂ�����L����
            // Route::get('/{code}', [CurrencyController::class, 'show'])->name('currencies.show');
        });
});