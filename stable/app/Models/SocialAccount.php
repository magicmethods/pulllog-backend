<?php

/**
 * PullLog SocialAccount Model
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @usage:
 * ```php
 * // SocialAccount でプロバイダ・ユーザーID一致を検索
 * $social = SocialAccount::providerUser('google', $googleId)->first();
 * ```
 */
class SocialAccount extends Model
{
    protected $table = 'social_accounts';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'provider_email',
        'avatar_url',
        'access_token',
        'refresh_token',
        'token_expires_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'user_id'           => 'int',
            'token_expires_at'  => 'datetime',
            // Laravelの暗号化キャスト（APP_KEY必須）
            'access_token'      => 'encrypted',
            'refresh_token'     => 'encrypted',
        ];
    }

    /** @return BelongsTo<User,SocialAccount> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* ---------- Scopes ---------- */

    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function scopeProvider($q, string $provider)
    {
        return $q->where('provider', $provider);
    }

    public function scopeProviderUser($q, string $provider, string $providerUserId)
    {
        return $q->where('provider', $provider)->where('provider_user_id', $providerUserId);
    }

    /* ---------- Utils ---------- */

    public function getIsExpiredAttribute(): ?bool
    {
        return $this->token_expires_at?->lte(now());
    }    

}
