<?php

/**
 * PullLog LogWithMoney Model
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogWithMoney extends Model
{
    protected $table = 'logs_with_money';

    // ビューは読み取り専用。PKは logs.id を使う（複合PKは使わない）
    protected $primaryKey = 'id';
    public $incrementing = false; // id はビュー経由では自動採番しない
    protected $keyType = 'int';

    // created_at/updated_at はビューに含まれているので true のままでOK
    public $timestamps = true;

    // 読み取り専用にする：create/update/delete を拒否
    protected static function booted(): void
    {
        static::creating(fn() => false);
        static::updating(fn() => false);
        static::saving(fn() => false);
        static::deleting(fn() => false);
    }

    protected function casts(): array
    {
        return [
            'id'               => 'int',
            'user_id'          => 'int',
            'app_id'           => 'int',
            'log_date'         => 'date',
            'total_pulls'      => 'int',
            'discharge_items'  => 'int',
            'expense_amount'   => 'int',    // 最小単位の整数
            'expense_decimal'  => 'string', // 文字列で保持（精度維持）
            'currency_code'    => 'string',
            'minor_unit'       => 'int',
            'drop_details'     => 'array',
            'tags'             => 'array',
            'images'           => 'array',
            'tasks'            => 'array',
            'created_at'       => 'datetime',
            'updated_at'       => 'datetime',
        ];
    }

    /** @return BelongsTo<App,LogWithMoney> */
    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class, 'app_id', 'id');
    }

    /** @return BelongsTo<User,LogWithMoney> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /* ---------- Scopes ---------- */

    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function scopeForApp($q, int $appId)
    {
        return $q->where('app_id', $appId);
    }

    public function scopeDateBetween($q, string|\DateTimeInterface $from, string|\DateTimeInterface $to)
    {
        return $q->whereBetween('log_date', [$from, $to]);
    }
}
