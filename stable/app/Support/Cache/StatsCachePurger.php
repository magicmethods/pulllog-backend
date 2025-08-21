<?php

namespace App\Support\Cache;

use App\Models\StatsCache;

trait StatsCachePurger
{
    /**
     * StatsCache を新旧キー両対応で掃除
     * - 新: stats:{key_version}:u:{userId}:app:{appDbId}:{start}:{end}:cur:{code}
     * - 旧: stats:{userId}:{appKey}:...
     */
    private function purgeStatsCacheForApp(int $userId, int $appDbId, string $appKey, ?string $oldCurrency = null): void
    {
        // 現行
        $nowPrefix = sprintf('stats:%s:u:%d:app:%d:', config('cache.key_version'), $userId, $appDbId);
        $nowSuffix = $oldCurrency ? sprintf(':cur:%s', $oldCurrency) : '';
        StatsCache::where('user_id', $userId)
            ->where('cache_key', 'like', $nowPrefix.'%'.$nowSuffix)
            ->delete();

        // レガシー互換
        $oldPrefix = sprintf('stats:%d:%s:', $userId, $appKey);
        StatsCache::where('user_id', $userId)
            ->where('cache_key', 'like', $oldPrefix.'%')
            ->delete();
    }
}
