<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

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

    protected function casts(): array
    {
        return [
            'user_id' => 'int',
            'token_expires_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
