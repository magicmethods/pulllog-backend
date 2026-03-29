<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GalleryUsageStat extends Model
{
    protected $table = 'gallery_usage_stats';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'bytes_used',
        'files_count',
    ];

    protected $casts = [
        'user_id' => 'int',
        'bytes_used' => 'int',
        'files_count' => 'int',
    ];
}
