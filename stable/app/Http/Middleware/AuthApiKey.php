<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('x-api-key');
        $validKey = config('api.api_key'); // config/api.php で 'api_key' を定義
        if (!$apiKey || $apiKey !== $validKey) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        return $next($request);
    }
}
