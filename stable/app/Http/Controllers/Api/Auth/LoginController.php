<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuthToken;
use App\Models\UserSession;
use App\Services\LocaleResolver;
use App\Services\UserResponseBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    /* CSRF TTL(分) を session.lifetime から取得 */
    private function csrfTtlMinutes(): int
    {
        // config/session.php の 'lifetime'（分）。未設定時は 120 分をデフォルト
        return (int) config('session.lifetime', 120);
    }
    /* CSRF トークン生成（暗号論的に安全） */
    private function newCsrfToken(): string
    {
        return bin2hex(random_bytes(32)); // 64文字のhex
    }
    /**
     * POST /auth/login
     * ログイン
     */
    public function login(Request $request): JsonResponse
    {
        // バリデーション
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
            'remember' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            $lang = LocaleResolver::resolve($request, null);
            return response()->json([
                'state'   => 'error',
                'message' => trans('auth.validation_failed', ['error' => $validator->errors()->first()], $lang),
                'user'    => null,
                'csrfToken' => null,
            ], 400);
        }

        // ユーザー検索
        $user = User::where('email', $request->email)->first();
        $lang = LocaleResolver::resolve($request, $user);
        if (!$user) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('auth.user_not_found', [], $lang),
                'user'    => null,
                'csrfToken' => null,
            ], 400);
        }

        // パスワード検証
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('auth.invalid_password', [], $lang),
                'user'    => null,
                'csrfToken' => null,
            ], 400);
        }

        // 論理削除 or 未認証
        if ($user->is_deleted || !$user->is_verified) {
            return response()->json([
                'state'   => 'error',
                'message' => trans('auth.account_invalid', [], $lang),
                'user'    => null,
                'csrfToken' => null,
            ], 401);
        }

        // Rememberトークンを生成
        $rememberToken = null;
        $expireAt = null;
        if ($request->boolean('remember')) {
            // 新しいRememberトークンを作成
            $rememberToken = Str::random(80);
            $expireAt = Carbon::now('UTC')->addDays(30);

            // AuthTokenモデルを更新（多重ログイン対策）
            // 既存のRememberトークンを削除
            AuthToken::where('user_id', $user->id)
                ->where('type', 'remember')
                ->delete();
            AuthToken::create([
                'user_id'    => $user->id,
                'token'      => $rememberToken,
                'type'       => 'remember',
                'expires_at' => $expireAt,
                'is_used'    => false,
                'ua'         => $request->header('User-Agent'),
                'ip'         => $request->ip(),
            ]);
            // Userモデルも更新
            $user->remember_token = $rememberToken;
        } else {
            // remember未チェック時、既存トークンを削除
            AuthToken::where('user_id', $user->id)
                ->where('type', 'remember')
                ->delete();
            $user->remember_token = null;
        }

        // last_login などを更新
        $user->last_login = Carbon::now('UTC');
        $user->last_login_ip = $request->ip();
        $user->last_login_ua = $request->header('User-Agent');
        $user->unread_notices = [];
        $user->save();

        // セッショントークン（CSRFトークン）を生成
        $csrfToken = $this->newCsrfToken();
        // 多重ログインを防ぐため、既存のセッションを削除
        UserSession::where('user_id', $user->id)->delete();
        $sessionData = [
            'csrf_token' => $csrfToken,
            'user_id'    => $user->id,
            'email'      => $user->email,
            'created_at' => Carbon::now('UTC'),
            'expires_at' => Carbon::now('UTC')->addMinutes($this->csrfTtlMinutes()),
        ];
        Log::debug('Creating new user session', $sessionData);
        UserSession::create($sessionData);

        // レスポンス用ユーザーデータを生成
        $userResponse = UserResponseBuilder::build($user);
        //Log::debug('Response user data', $userResponse);

        // レスポンス
        return response()->json([
            'state'         => 'success',
            'message'       => null,
            'user'          => $userResponse,
            'csrfToken'     => $csrfToken,
            'rememberToken' => $rememberToken,
            'rememberTokenExpires' => $expireAt ? $expireAt->toIso8601String() : null,
        ]);
    }
    /**
     * POST /auth/autologin
     * 自動ログイン
     */
    public function autologin(Request $request): JsonResponse
    {
        // トークン取得（クッキー > ボディ > リクエストヘッダ）
        $rememberToken = $request->cookie('remember_token')
            ?? $request->input('remember_token')
            ?? $request->header('x-remember-token');

        $lang = LocaleResolver::resolve($request, null);
        Log::debug('Attempting auto-login', [
            'remember_token' => $rememberToken,
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'locale' => $lang,
        ]);
        if (!$rememberToken) {
            // トークンがない場合でもエラーにしない
            return response()->json([
                'state' => 'warn', // 警告状態で応答
                'message' => trans('auth.no_remember_token', [], $lang),
            ], 400);
        }

        // 有効なrememberトークン検索
        $now = Carbon::now('UTC');
        $authToken = AuthToken::where('token', $rememberToken)
            ->where('type', 'remember')
            ->where('is_used', false)
            ->where('expires_at', '>', $now)
            ->first();

        if (!$authToken) {
            // トークンが無効でもエラーにしない
            return response()->json([
                'state' => 'warn', // 警告状態で応答
                'message' => trans('auth.invalid_or_expired_token', [], $lang)
            ], 400);
        }

        // ユーザー確認
        $user = User::find($authToken->user_id);
        $lang = LocaleResolver::resolve($request, $user);
        if (!$user || $user->is_deleted || !$user->is_verified) {
            // ユーザーが存在しない、削除済み、未認証の場合はエラー
            return response()->json([
                'state' => 'error',
                'message' => trans('auth.user_not_found_or_invalid', [], $lang)
            ], 401);
        }

        // トークンローテーション
        DB::beginTransaction();
        try {
            // もし多重ログイン許可時は既存トークンを使用済みに
            //$authToken->is_used = true;
            //$authToken->save();
            // 既存Rememberトークンを全て削除
            AuthToken::where('user_id', $user->id)
                ->where('type', 'remember')
                ->delete();

            // 新しいrememberトークン発行
            $newRememberToken = Str::random(80);
            $newExpire = $now->copy()->addDays(30);

            AuthToken::create([
                'user_id'    => $user->id,
                'token'      => $newRememberToken,
                'type'       => 'remember',
                'expires_at' => $newExpire,
                'is_used'    => false,
                'ua'         => $request->userAgent(),
                'ip'         => $request->ip(),
            ]);

            // 新しいCSRFトークン(セッション)も発行
            $csrfToken = $this->newCsrfToken();
            UserSession::where('user_id', $user->id)->delete();
            UserSession::create([
                'csrf_token' => $csrfToken,
                'user_id'    => $user->id,
                'email'      => $user->email,
                'created_at' => $now,
                'expires_at' => $now->copy()->addMinutes($this->csrfTtlMinutes()),
            ]);

            // レスポンス用ユーザーデータ生成
            $userResponse = UserResponseBuilder::build($user);

            DB::commit();

            // レスポンス（新しいrememberトークン・CSRFトークンを返す。Cookie設定はフロントで）
            return response()->json([
                'state'         => 'success',
                'message'       => null,
                'user'          => $userResponse,
                'csrfToken'     => $csrfToken,
                'rememberToken' => $newRememberToken,
                'rememberTokenExpires' => $newExpire->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json([
                'state' => 'error',
                'message' => trans('auth.auto_login_failed', ['error' => $e->getMessage()], $lang),
            ], 500);
        }
    }
    /**
     * GET /auth/csrf/refresh
     * Remember が有効なログインユーザー向けに CSRF トークンを再発行する
     * - ルート: routes/api.php で 'web' ミドルウェアを付与、'auth.csrf' は外す
     */
    public function refreshCsrf(Request $request): JsonResponse
    {
        // Rememberトークンの取得（クッキー/リクエストボディ/ヘッダーどこでも可）
        $rememberToken = $request->cookie('remember_token')
            ?? $request->input('remember_token')
            ?? $request->header('x-remember-token');
        $userId = null;
        $lang = LocaleResolver::resolve($request, null);
        if ($rememberToken) {
            $authToken = AuthToken::where('token', $rememberToken)
                ->where('type', 'remember')
                ->where('is_used', false)
                ->first();
            if ($authToken) {
                $userId = $authToken->user_id;
            }
        }
        if (!$userId) {
            // ユーザーが有効なRememberトークンを持っていない場合はエラー
            return response()->json([
                'state' => 'error',
                'message' => trans('auth.csrf_refresh_failed', ['error' => 'Invalid remember token'], $lang),
            ], 401);
        }
        $expiredCsrfToken = $request->header('x-csrf-token') ?? $request->input('expired_csrf_token');
        $userSession = null;
        if ($expiredCsrfToken) {
            $userSession = UserSession::where('csrf_token', $expiredCsrfToken)->first();
        }
        if (!$userSession || $userSession->user_id !== $userId) {
            // CSRFトークンが無効またはユーザーIDが一致しない場合はエラー
            return response()->json([
                'state' => 'error',
                'message' => trans('auth.csrf_refresh_failed', ['error' => 'Invalid CSRF token'], $lang),
            ], 401);
        }
        // ユーザー検索
        $user = User::where('id', $userId)
            ->where('is_deleted', false)
            ->first();
        if (!$user) {
            // ユーザーが見つからない場合はエラー
            return response()->json([
                'state' => 'error',
                'message' => trans('auth.csrf_refresh_failed', ['error' => 'User not found'], $lang),
            ], 404);
        }

        Log::debug('Refresh CSRF Token', [
            'request' => $request->all(),
            'user_session' => $userSession,
            'user' => $user
        ]);

        $lang = LocaleResolver::resolve($request, $user);
        $now = now('UTC'); // ミドルウェアと同じUTC基準に合わせる
        $ttlMinutes = $this->csrfTtlMinutes();
        $expiresAt = $now->copy()->addMinutes($ttlMinutes);

        // 新規トークンを先に生成
        $newToken = $this->newCsrfToken();

        try {
            DB::transaction(function () use ($user, $expiredCsrfToken, $now, $expiresAt, $newToken) {
                // 古いトークンがこのユーザーのものなら無効化（存在しなくてもOK）
                if ($expiredCsrfToken) {
                    UserSession::where('csrf_token', $expiredCsrfToken)
                        ->where('user_id', $user->id)
                        ->delete();
                }

                // 期限切れのセッションを掃除
                UserSession::where('user_id', $user->id)
                    ->where('expires_at', '<=', $now)
                    ->delete();

                // 新しいCSRFセッションを登録
                UserSession::create([
                    'csrf_token' => $newToken,
                    'user_id'    => $user->id,
                    'email'      => $user->email,
                    'created_at' => $now,
                    'expires_at' => $expiresAt,
                ]);
            });

            return response()->json([
                'csrfToken' => $newToken,
                'expiresAt' => $expiresAt->toIso8601String(),
            ], 200);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'state' => 'error',
                'message' => trans('auth.csrf_refresh_failed', ['error' => $e->getMessage()], $lang),
            ], 500);
        }
    }

}
