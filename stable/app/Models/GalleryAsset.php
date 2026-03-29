<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class GalleryAsset extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'gallery_assets';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'app_id',
        'log_id',
        'disk',
        'path',
        'thumb_path_small',
        'thumb_path_large',
        'mime',
        'bytes',
        'bytes_thumb_small',
        'bytes_thumb_large',
        'width',
        'height',
        'hash_sha256',
        'title',
        'description',
        'tags',
        'visibility',
    ];

    protected $casts = [
        'user_id' => 'int',
        'app_id' => 'int',
        'log_id' => 'int',
        'bytes' => 'int',
        'bytes_thumb_small' => 'int',
        'bytes_thumb_large' => 'int',
        'width' => 'int',
        'height' => 'int',
        'tags' => 'array',
    ];

    protected $appends = ['total_bytes'];

    protected static function booted(): void
    {
        static::creating(function (GalleryAsset $asset): void {
            if (!$asset->getKey()) {
                $asset->setAttribute($asset->getKeyName(), (string) Str::uuid());
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(Log::class);
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    public function link(): HasOne
    {
        return $this->hasOne(GalleryAssetLink::class, 'asset_id');
    }

    public function getTotalBytesAttribute(): int
    {
        return (int) ($this->bytes ?? 0)
            + (int) ($this->bytes_thumb_small ?? 0)
            + (int) ($this->bytes_thumb_large ?? 0);
    }
}
