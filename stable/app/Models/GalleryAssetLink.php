<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryAssetLink extends Model
{
    use HasFactory;

    protected $table = 'gallery_asset_links';

    protected $fillable = [
        'asset_id',
        'code',
        'expire_at',
        'last_accessed_at',
        'hit_count',
    ];

    protected $casts = [
        'expire_at' => 'immutable_datetime',
        'last_accessed_at' => 'immutable_datetime',
        'hit_count' => 'int',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(GalleryAsset::class, 'asset_id');
    }
}
