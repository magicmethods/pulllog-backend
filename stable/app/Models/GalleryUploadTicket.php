<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryUploadTicket extends Model
{
    use HasFactory;

    protected $table = 'gallery_upload_tickets';
    protected $dateFormat = 'Y-m-d H:i:sP';

    protected $fillable = [
        'user_id',
        'token',
        'app_id',
        'file_name',
        'expected_bytes',
        'mime',
        'max_bytes',
        'visibility',
        'log_id',
        'tags',
        'meta',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'app_id' => 'int',
        'meta' => 'array',
        'tags' => 'array',
        'expires_at' => 'immutable_datetime',
        'used_at' => 'immutable_datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(Log::class);
    }

    protected function isExpired(): Attribute
    {
        return Attribute::get(fn () => $this->expires_at instanceof CarbonImmutable
            ? $this->expires_at->isPast()
            : false);
    }
}
