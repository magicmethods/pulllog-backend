<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SocialAccount;
use App\Models\AuthToken;
use App\Models\UserSession;
use App\Services\OAuth\GoogleOauthService;
use App\Services\LocaleResolver;
use App\Services\UserResponseBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class OauthController extends Controller
{
    public function googleExchange(Request $request, GoogleOauthService $google): JsonResponse
    {
        // auth.apikey ミドルウェアは /auth 配下に掛かっている前提
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'code_verifier' => 'required|string',
            'redirect_uri' => 'required|url',
            'remember' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            $lang = LocaleResolver::resolve($request, null);
            return response()->json([
                'state' => 'error',
                'message' => trans('auth.validation_failed', ['error' => $validator->errors()->first()], $lang),
            ], 400);
        }

        $code = (string)$request->input('code');
        $codeVerifier = (string)$request->input('code_verifier');
        $redirectUri = (string)$request->input('redirect_uri');
        $remember = $request->boolean('remember', true); // OAuthは既定でRemember ONでもよい

        try {
            // 1) トークン交換
            $token = $google->exchangeCode($code, $codeVerifier, $redirectUri);
            $idToken = (string)($token['id_token'] ?? '');
            $accessToken = (string)($token['access_token'] ?? '');
            $refreshToken = isset($token['refresh_token']) ? (string)$token['refresh_token'] : null;
            $expiresIn = isset($token['expires_in']) ? (int)$token['expires_in'] : null;

            if ($idToken === '' || $accessToken === '') {
                throw new \RuntimeException('id_token or access_token is empty');
            }

            // 2) id_token 検証
            $claims = $google->verifyIdToken($idToken);

            $provider = 'google';
            $providerUserId = (string)($claims['sub'] ?? '');
            $email = (string)($claims['email'] ?? '');
            $name = (string)($claims['name'] ?? '');
            $picture = (string)($claims['picture'] ?? '');

            if ($providerUserId === '' || $email === '') {
                throw new \RuntimeException('Required claims are missing');
            }

            // 3) ユーザー作成／紐付け
            DB::beginTransaction();

            $link = SocialAccount::where('provider', $provider)
                ->where('provider_user_id', $providerUserId)
                ->first();

            if ($link) {
                $user = $link->user;
            } else {
                $user = User::where('email', $email)->first();

                if (!$user) {
                    $lang = LocaleResolver::resolve($request, null);
                    $user = new User([
                        'email' => $email,
                        'password' => Str::random(32),
                        'name' => $name !== '' ? $name : Str::before($email, '@'),
                        'avatar_url' => null,
                        'roles' => ['user'],
                        'plan_id' => 1, // 1: 無料プラン
                        'plan_expiration' => Carbon::now()->addYear(), // 1年後に設定
                        'language' => $lang,
                        'theme' => 'light',
                        'home_page' => '/apps',
                        'last_login' => null,
                        'last_login_ip' => null,
                        'last_login_ua' => null,
                        'is_deleted' => false,
                        'is_verified' => true,
                        'remember_token' => null,
                        'unread_notices' => [],
                        'email_verified_at' => Carbon::now('UTC'),
                    ]);
                    $user->save();
                }

                SocialAccount::create([
                    'user_id' => $user->id,
                    'provider' => $provider,
                    'provider_user_id' => $providerUserId,
                    'provider_email' => $email,
                    'avatar_url' => $picture !== '' ? $picture : null,
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_expires_at' => $expiresIn ? Carbon::now('UTC')->addSeconds($expiresIn) : null,
                ]);
            }

            // 4) 最終ログイン更新
            $user->last_login = Carbon::now('UTC');
            $user->last_login_ip = $request->ip();
            $user->last_login_ua = $request->userAgent();
            $user->save();

            // 5) Remember Token（任意）
            $rememberToken = null;
            $rememberExpire = null;
            if ($remember) {
                AuthToken::where('user_id', $user->id)->where('type', 'remember')->delete();
                $rememberToken = Str::random(80);
                $rememberExpire = Carbon::now('UTC')->addDays(30);
                AuthToken::create([
                    'user_id' => $user->id,
                    'token' => $rememberToken,
                    'type' => 'remember',
                    'expires_at' => $rememberExpire,
                    'is_used' => false,
                    'ua' => $request->userAgent(),
                    'ip' => $request->ip(),
                ]);
                $user->remember_token = $rememberToken;
                $user->save();
            } else {
                AuthToken::where('user_id', $user->id)->where('type', 'remember')->delete();
                $user->remember_token = null;
                $user->save();
            }

            // 6) CSRF（DBセッション）発行
            UserSession::where('user_id', $user->id)->delete();
            $csrfToken = hash('sha256', uniqid('', true) . Str::random(32));
            UserSession::create([
                'csrf_token' => $csrfToken,
                'user_id' => $user->id,
                'email' => $user->email,
                'created_at' => Carbon::now('UTC'),
                'expires_at' => Carbon::now('UTC')->addHours(2),
            ]);

            $userResponse = UserResponseBuilder::build($user);

            DB::commit();

            return response()->json([
                'state' => 'success',
                'message' => null,
                'user' => $userResponse,
                'csrfToken' => $csrfToken,
                'rememberToken' => $rememberToken,
                'rememberTokenExpires' => $rememberExpire ? $rememberExpire->toIso8601String() : null,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            $lang = LocaleResolver::resolve($request, null);
            return response()->json([
                'state' => 'error',
                'message' => trans('auth.oauth_failed', ['error' => $e->getMessage()], $lang),
            ], 400);
        }
    }
}
