<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DemoGuard
{
    /** @var array<string> 書き込みと見なすHTTPメソッド */
    private array $writeMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** @var array<string> デモでも許可するパス（先頭一致） */
    private array $allowPrefixes = [
        '/logout', // 許可したいパスがあれば追加
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 未ログインは素通し（他ミドルウェアで制御される想定）
        if (!$user || !$this->isDemoUser($user)) {
            return $next($request);
        }

        // ヘッダで「デモモード」を明示
        $request->headers->set('X-Demo-Mode', 'true');

        // 許可パスなら通す
        foreach ($this->allowPrefixes as $prefix) {
            if (\str_starts_with($request->getPathInfo(), $prefix)) {
                return $next($request);
            }
        }

        // 書き込み系は拒否（204 No Contentにして“成功風”のUXを保つ）
        if (\in_array($request->getMethod(), $this->writeMethods, true)) {
            return response('', 204, [
                'X-Demo-Mode'    => 'true',
                'X-Demo-Blocked' => 'write-operation',
            ]);
        }

        // それ以外（GET/HEAD/OPTIONS）は通す
        $response = $next($request);
        $response->headers->set('X-Demo-Mode', 'true');
        return $response;
    }

    /**
     * デモユーザー判定
     * - users.roles に demo があるかどうか。なければ env('DEMO_EMAIL') にフォールバック
     */
    private function isDemoUser($user): bool
    {
        if (property_exists($user, 'roles') && in_array('demo', $user->roles, true)) {
            return true;
        }

        $demoEmail = (string) config('demo.demo_email', env('DEMO_EMAIL'));
        if ($demoEmail !== '' && \strtolower($user->email ?? '') === \strtolower($demoEmail)) {
            return true;
        }

        $demoUserIds = (array) config('demo.demo_user_ids', []);
        if (!empty($demoUserIds) && \in_array((int) $user->id, array_map('intval', $demoUserIds), true)) {
            return true;
        }

        return false;
    }
}
