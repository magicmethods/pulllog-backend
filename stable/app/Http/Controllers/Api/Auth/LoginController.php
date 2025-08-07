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
        // CSRFトークンはPrimaryKeyのため一意性担保が必要
        $csrfToken = hash('sha256', uniqid('', true) . Str::random(32));
        // 多重ログインを防ぐため、既存のセッションを削除
        UserSession::where('user_id', $user->id)->delete();
        $sessionData = [
            'csrf_token' => $csrfToken,
            'user_id'    => $user->id,
            'email'      => $user->email,
            'created_at' => Carbon::now('UTC'),
            'expires_at' => Carbon::now('UTC')->addHours(2), // 2時間有効
        ];
        Log::debug('Creating new user session', $sessionData);
        UserSession::create($sessionData);

        // レスポンス用ユーザーデータを生成
        $userResponse = UserResponseBuilder::build($user);

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
            $csrfToken = hash('sha256', uniqid('', true) . Str::random(32));
            UserSession::where('user_id', $user->id)->delete();
            UserSession::create([
                'csrf_token' => $csrfToken,
                'user_id'    => $user->id,
                'email'      => $user->email,
                'created_at' => $now,
                'expires_at' => $now->copy()->addHours(2),
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
     * POST /auth/logout
     * ログアウト
     */
    public function logout(Request $request): JsonResponse
    {
        // CSRFトークンから現在のセッション（UserSession）を取得
        $csrfToken = $request->header('x-csrf-token') ?? $request->input('csrf_token');
        $userSession = null;
        if ($csrfToken) {
            $userSession = UserSession::where('csrf_token', $csrfToken)->first();
        }
        $userId = $userSession?->user_id;

        // Rememberトークンの取得（クッキー/リクエストボディ/ヘッダーどこでも可）
        $rememberToken = $request->cookie('remember_token')
            ?? $request->input('remember_token')
            ?? $request->header('x-remember-token');

        // RememberトークンをAuthTokenで失効させる（is_used=true）
        // - ログイン時に失効トークンは削除されるのでここでは更新のみでOk
        if ($rememberToken) {
            $authToken = AuthToken::where('token', $rememberToken)
                ->where('type', 'remember')
                ->where('is_used', false)
                ->first();
            if ($authToken) {
                $authToken->is_used = true;
                $authToken->save();
            }
        }
        // UserSession（セッション）も削除
        if ($userSession) {
            $userSession->delete();
        } elseif ($userId) {
            // 念のため: user_idで全部消す
            UserSession::where('user_id', $userId)->delete();
        }

        $lang = LocaleResolver::resolve($request, null);
        // レスポンス
        return response()->json([
            'success' => true,
            'message' => trans('auth.logged_out_successfully', [], $lang),
        ]);
    }

}
