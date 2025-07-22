<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        // CSRFトークンはDBから取得する
        $validToken = $request->user_sessions() ? $request->user_sessions()->csrf_token : null;
        if (!$csrfToken || $csrfToken !== $validToken) {
            return response()->json(['message' => 'CSRF token mismatch'], 403);
        }
        return $next($request);
    }
}
