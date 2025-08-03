<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use App\Models\UserSession;
use App\Services\LocaleResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthCsrfToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $csrfToken = $request->header('x-csrf-token');
        // CSRFトークンはDB（UserSessionモデル）から取得する
        $now = Carbon::now('UTC');
        $validSession = UserSession::where('csrf_token', $csrfToken)
            ->where('expires_at', '>', $now)
            ->first();
        $user = User::find($validSession->user_id ?? null);
        $lang = LocaleResolver::resolve($request, $user);
        /*
        Log::debug('AuthCsrfToken@handle', [
            'csrfToken' => $csrfToken,
            'validSession' => $validSession,
            'user' => $user,
            'request' => $request->all(),
        ]);
        */
        if (!$csrfToken || !$validSession || !$user) {
            return response()->json(['message' => trans('auth.csrf_token_mismatch', [], $lang)], 403);
        }

        // CSRFトークンが有効な場合、リクエストにユーザー情報をセット
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return $next($request);
    }
}
