<?php

/**
 * PullLog StatsCache Model
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int|null $user_id
 * @property string $cache_key
 * @property string $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @usage:
 * ```php
 * // 統計キャッシュ（5分TTL）
 * $stats = StatsCache::rememberJson($userId, 'dash:summary:v1', 300, function () use ($userId) {
 *     // 重い集計処理...
 *     return [
 *         'total_logs' => \App\Models\Log::forUser($userId)->count(),
 *         // ...
 *     ];
 * });
 * ```
 */
class StatsCache extends Model
{
	protected $table = 'stats_cache';
	protected $primaryKey = 'cache_key';
	public $incrementing = false;
	protected $keyType = 'string';
	// created_at / updated_at あり → デフォルトの timestamps = true のまま

	protected $fillable = [
		'cache_key',
		'user_id',
		'value',
	];

    protected function casts(): array
	{
		return [
			'user_id' => 'int',
			// TEXT でも 'array' キャストで JSON 入出力が可能（内部はjson_encode/Decode）
			'value'   => 'array',
		];
	}

	/* ---------- Scopes ---------- */

    public function scopeForUser($q, ?int $userId)
    {
        return $userId === null ? $q->whereNull('user_id') : $q->where('user_id', $userId);
    }

    public function scopeKeyPrefix($q, string $prefix)
    {
        return $q->where('cache_key', 'like', $prefix . '%');
    }

    public function scopeFreshSince($q, \DateTimeInterface $since)
    {
        return $q->where('updated_at', '>=', $since);
    }

    /* ---------- Utils ---------- */

	/** シンプルな remember（TTLは秒）。updated_at 基準の鮮度チェック */
    public static function rememberJson(?int $userId, string $key, int $ttlSeconds, callable $compute): array
    {
        $row = static::forUser($userId)->find($key);
        $fresh = $row && $row->updated_at && $row->updated_at->gte(now()->subSeconds($ttlSeconds));

        if ($fresh) {
            return $row->value ?? [];
        }

        $data = $compute(); // array想定
        static::updateOrCreate(
            ['cache_key' => $key, 'user_id' => $userId],
            ['value' => $data] // casts により JSON で保存
        );

        return $data ?? [];
    }

}
