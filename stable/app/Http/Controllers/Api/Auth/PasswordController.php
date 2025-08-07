<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuthToken;
use App\Services\LocaleResolver;
use App\Services\AuthMailService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
//use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
//use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class PasswordController extends Controller
{
    /**
     * POST /auth/password
     * パスワードリセットリクエスト
     */
    public function requestReset(Request $request): JsonResponse
    {
        $lang = LocaleResolver::resolve($request, null);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);
        if ($validator->fails()) {
            Log::warning('PasswordController@requestReset:ValidationError', [
                'email' => $request->input('email'),
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => trans('auth.invalid_request', [], $lang),
            ], 400);
        }

        $email = $request->input('email');

        // Rate limit check (1時間に5回まで)
        $key = 'password-reset:'.$email;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            Log::warning('PasswordController@requestReset:RateLimitExceeded', [
                'email' => $email,
                'ip' => $request->ip()
            ]);
            return response()->json([
                'success' => false,
                'message' => trans('auth.too_many_requests', [], $lang),
            ], 429);
        }
        RateLimiter::hit($key, 3600);

        $user = User::where('email', $email)->first();

        if (!$user || $user->is_deleted || !$user->is_verified) {
            Log::info('PasswordController@requestReset:InvalidUser', [
                'email' => $email,
                'user_exists' => (bool)$user,
                'is_deleted' => $user?->is_deleted,
                'is_verified' => $user?->is_verified,
                'ip' => $request->ip(),
            ]);
            return response()->json(['success' => true]);
        }

        // 既存のresetトークンはis_used化
        AuthToken::where('user_id', $user->id)
            ->where('type', 'reset')
            ->where('is_used', false)
            ->update(['is_used' => true]);

        // トークン生成
        $tokenValue = bin2hex(random_bytes(32));
        $code = strtoupper(substr(uniqid(), 0, 6));
        $expiresAt = Carbon::now('UTC')->addDay();

        AuthToken::create([
            'user_id' => $user->id,
            'token' => $tokenValue,
            'type' => 'reset',
            'code' => $code,
            'expires_at' => $expiresAt,
            'is_used' => false,
            'ip' => $request->ip(),
            'ua' => $request->userAgent(),
        ]);

        // メール送信
        AuthMailService::send($user, $tokenValue, $code, 'reset', $user->language);
        /*
        Log::info('PasswordController@requestReset:Success', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);
        */
        return response()->json(['success' => true]);
    }
    /**
     * PUT /auth/password
     * パスワードリセット
     */
    public function reset(Request $request): JsonResponse
    {
        $lang = LocaleResolver::resolve($request, null);

        $validator = Validator::make($request->all(), [
            'token'    => 'required|string',
            'type'     => 'required|string|in:reset',
            'code'     => 'required|string',
            'password' => 'required|string|min:8', // 必要なら複雑性ルール追加
        ]);
        if ($validator->fails()) {
            Log::warning('PasswordController@reset:ValidationError', [
                'ip' => $request->ip(),
                'data' => $request->all(),
            ]);
            return response()->json([
                'success' => false,
                'message' => trans('auth.invalid_request', [], $lang),
            ], 400);
        }

        // トークン取得
        $authToken = AuthToken::where('token', $request->input('token'))->first();
        if (!$authToken) {
            Log::warning('PasswordController@reset:TokenNotFound', [
                'token' => $request->input('token'),
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => trans('auth.invalid_token', [], $lang),
            ], 401);
        }

        // type確認
        if ($authToken->type->value !== $request->input('type')) {
            Log::warning('PasswordController@reset:TokenTypeMismatch', [
                'token' => $authToken->token->value,
                'type_db' => $authToken->type,
                'type_req' => $request->input('type'),
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => trans('auth.invalid_request', [], $lang),
            ], 400);
        }

        // 有効期限
        if ($authToken->expires_at->isPast()) {
            Log::info('PasswordController@reset:TokenExpired', [
                'token' => $authToken->token,
                'expires_at' => $authToken->expires_at,
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => trans('auth.expired_token', [], $lang),
            ], 400);
        }

        // is_used
        if ($authToken->is_used) {
            Log::info('PasswordController@reset:TokenAlreadyUsed', [
                'token' => $authToken->token,
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => trans('auth.token_already_used', [], $lang),
            ], 400);
        }

        // 認証コードチェック
        if ($authToken->code !== $request->input('code')) {
            // 失敗カウント増加
            $authToken->failed_attempts++;
            // 3回以上でトークン失効
            if ($authToken->failed_attempts >= 3) {
                $authToken->is_used = true;
                Log::notice('PasswordController@reset:TokenBlockedAfterFailures', [
                    'token' => $authToken->token,
                    'failed_attempts' => $authToken->failed_attempts,
                    'ip' => $request->ip(),
                ]);
            }
            $authToken->save();

            return response()->json([
                'success' => false,
                'message' => trans('auth.invalid_code', [], $lang),
                'blocked' => $authToken->is_used,
                'failed_attempts' => $authToken->failed_attempts,
            ], 400);
        }

        // ユーザー特定
        $user = $authToken->user;
        if (!$user || $user->is_deleted || !$user->is_verified) {
            Log::warning('PasswordController@reset:UserInvalid', [
                'user_id' => $authToken->user_id,
                'is_deleted' => $user?->is_deleted,
                'is_verified' => $user?->is_verified,
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => trans('auth.invalid_user', [], $lang),
            ], 400);
        }

        // パスワード更新
        $user->password = Hash::make($request->input('password'));
        $user->save();

        // トークンを使用済みに
        $authToken->is_used = true;
        $authToken->save();

        Log::info('PasswordController@reset:Success', [
            'user_id' => $user->id,
            'email'   => $user->email,
            'ip'      => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
        ]);
    }

}