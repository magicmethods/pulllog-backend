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
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RegisterController extends Controller
{
    /**
     * POST /auth/register
     * アカウント新規登録
     */
    public function register(Request $request): JsonResponse
    {
        // バリデーション
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:50',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'language' => 'nullable|string|in:ja,en,zh',
        ]);

        $lang = LocaleResolver::resolve($request, null);
        if ($validator->fails()) {
            return response()->json([
                'state' => 'error',
                'message' => trans('auth.validation_failed', ['error' => $validator->errors()->first()], $lang),
            ], 400);
        }

        DB::beginTransaction();
        try {
            // ユーザー新規作成
            $user = User::create([
                'email'             => $request->input('email'),
                'password'          => Hash::make($request->input('password')),
                'name'              => $request->input('name'),
                'avatar_url'        => null,
                'roles'             => ['user'],
                'plan_id'           => 1, // 1: 無料プラン
                'plan_expiration'   => Carbon::now()->addYear(), // 1年後に設定
                'language'          => $request->input('language', 'en'),
                'theme'             => $request->input('theme', 'light'),
                'home_page'         => $request->input('home_page', '/apps'),
                'last_login'        => null,
                'last_login_ip'     => null,
                'last_login_ua'     => null,
                'is_deleted'        => false,
                'is_verified'       => false,
                'remember_token'    => null,
                'unread_notices'    => [],
                'email_verified_at' => null,
            ]);

            // メール認証トークンの生成
            $token = Str::random(64);
            $expiredAt = Carbon::now()->addHours(24); // 24時間有効

            $authToken = AuthToken::create([
                'user_id'    => $user->id,
                'token'      => $token,
                'type'       => 'signup',
                'code'       => null,
                'is_used'    => false,
                'expires_at' => $expiredAt,
            ]);

            // メール送信
            $lang = LocaleResolver::resolve($request, $user);
            AuthMailService::send($user, $token, null, 'signup', $lang);

            DB::commit();

            return response()->json([
                'state'   => 'success',
                'message' => trans('auth.registration_completed', [], $lang),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            // ログ出力
            report($e);
            return response()->json([
                'state' => 'error',
                //'message' => 'Registration failed: ' . $e->getMessage(),
                'message' => trans('auth.registration_failed', ['error' => $e->getMessage()], $lang),
            ], 500);
        }

        // This shouldn't happen
        abort(500);
    }
    /**
     * POST /auth/verify
     * メール認証
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        // クエリパラメータ取得
        $token = $request->input('token');
        $type = $request->input('type');

        $lang = LocaleResolver::resolve($request, null);
        Log::debug('Verify email: ', ['request' => $request->all(), 'token' => $token, 'type' => $type]);

        // パラメータバリデーション
        if (!$token || !$type || !in_array($type, ['signup', 'reset'], true)) {
            Log::warning('PasswordController@verifyEmail:InvalidRequest', [
                'ip' => $request->ip(),
                'data' => $request->all(),
            ]);
            return response()->json([
                'success' => false,
                'message' => trans('auth.invalid_request', [], $lang),
            ], 400);
        }

        // トークン検索
        $authToken = AuthToken::where('token', $token)->first();
        if (!$authToken) {
            Log::warning('PasswordController@verifyEmail:TokenNotFound', [
                'ip' => $request->ip(),
                'token' => $token,
            ]);
            return response()->json([
                'success' => false,
                'message' => trans('auth.invalid_token', [], $lang),
            ], 401);
        }

        // タイプ不一致
        if ($authToken->type->value !== $type) {
            Log::warning('PasswordController@verifyEmail:TokenTypeMismatch', [
                'token' => $authToken->token->value,
                'type_db' => $authToken->type,
                'type_req' => $type,
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => trans('auth.invalid_token_type', [], $lang),
            ], 400);
        }

        // 有効期限チェック
        if ($authToken->expires_at < now()) {
            Log::warning('PasswordController@verifyEmail:TokenExpired', [
                'token' => $authToken->token,
                'expires_at' => $authToken->expires_at,
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => trans('auth.expired_token', [], $lang),
            ], 400);
        }

        // 既に使用済みか
        if ($authToken->is_used) {
            Log::warning('PasswordController@verifyEmail:TokenAlreadyUsed', [
                'token' => $authToken->token,
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => trans('auth.token_already_used', [], $lang),
            ], 400);
        }

        // signup認証の場合、ユーザーの認証済みフラグをON
        if ($type === 'signup') {
            $user = User::find($authToken->user_id);
            if (!$user) {
                Log::warning('PasswordController@verifyEmail:UserNotFound', [
                    'user_id' => $authToken->user_id,
                    'ip' => $request->ip(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => trans('auth.user_not_found_short', [], $lang),
                ], 404);
            }
            if ($user->is_deleted) {
                Log::warning('PasswordController@verifyEmail:UserDeleted', [
                    'user_id' => $user->id,
                    'ip' => $request->ip(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => trans('auth.user_account_deleted', [], $lang),
                ], 400);
            }
            $user->is_verified = true;
            $user->email_verified_at = now();
            $user->save();

            // トークンを使用済みに
            $authToken->is_used = true;
            $authToken->save();

            // 正常完了
            return response()->json([
                'success' => true,
                'message' => trans('auth.verification_succeeded', [], $lang),
            ]);
        }
        // reset認証の場合、追加入力画面に移行するため成功レスポンスのみ返す
        return response()->json([
            'success' => true,
        ]);
    }

}
