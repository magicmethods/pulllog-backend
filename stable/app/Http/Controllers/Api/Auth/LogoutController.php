<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuthToken;
use App\Models\UserSession;
use App\Services\LocaleResolver;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogoutController extends Controller
{
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

        // ユーザー検索
        $user = User::where('id', $userId)->first();
        $lang = LocaleResolver::resolve($request, $user ?? null);

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

        Log::info('User logged out', [
            'user_id' => $userId,
            'csrf_token' => $csrfToken,
            'remember_token' => $rememberToken,
            'user_session_deleted' => $userSession ? true : false,
        ]);
        
        // レスポンス
        return response()->json([
            'success' => true,
            'message' => trans('auth.logged_out_successfully', [], $lang),
        ]);
    }

}
