<?php

/**
 * PullLog UserSession Model
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $csrf_token
 * @property int $user_id
 * @property string $email
 * @property Carbon $created_at
 * @property Carbon $expires_at
 * 
 * @usage:
 * ```php
 * // セッション検証
 * $valid = UserSession::byToken($token)->notExpired()->exists();
 * ```
 */
class UserSession extends Model
{
	protected $table = 'user_sessions';
	protected $primaryKey = 'csrf_token';
	public $incrementing = false;
    protected $keyType = 'string';
	public $timestamps = false;

	protected $fillable = [
        'csrf_token',
		'user_id',
		'email',
        'created_at',
		'expires_at'
	];

	protected $hidden = [
		'csrf_token', // 外部返却時は隠す
	];

    protected function casts(): array
    {
        return [
            'user_id'    => 'int',
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User,UserSession> */
	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}

    /* ---------- Scopes ---------- */

    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function scopeNotExpired($q)
    {
        return $q->where('expires_at', '>', now());
    }

    public function scopeByToken($q, string $token)
    {
        return $q->where('csrf_token', $token);
    }

    /* ---------- Utils ---------- */

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lte(now());
    }

    public function getRemainingSecondsAttribute(): ?int
    {
        return $this->expires_at?->diffInSeconds(now(), absolute: false) !== null
            ? max(0, $this->expires_at->diffInSeconds(now(), false) * -1)
            : null;
    }

}
