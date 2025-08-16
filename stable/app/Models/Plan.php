<?php

/**
 * PullLog Plan Model
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $max_apps
 * @property int $max_app_name_length
 * @property int $max_app_desc_length
 * @property int $max_log_tags
 * @property int $max_log_tag_length
 * @property int $max_log_text_length
 * @property int $max_logs_per_app
 * @property int $max_storage_mb
 * @property int $price_per_month
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @usage:
 * ```php
 * // アクティブなプランのユーザー数
 * $plan = Plan::active()->first();
 * $count = $plan?->users()->count();
 * ```
 */
class Plan extends Model
{
	protected $table = 'plans';

	protected $fillable = [
		'name',
		'description',
		'max_apps',
		'max_app_name_length',
		'max_app_desc_length',
		'max_log_tags',
		'max_log_tag_length',
		'max_log_text_length',
		'max_logs_per_app',
		'max_storage_mb',
		'price_per_month',
		'is_active',
	];

    protected function casts(): array
	{
		return [
			'max_apps' 			   => 'int',
			'max_app_name_length'  => 'int',
			'max_app_desc_length'  => 'int',
			'max_log_tags' 		   => 'int',
			'max_log_tag_length'   => 'int',
			'max_log_text_length'  => 'int',
			'max_logs_per_app'     => 'int',
			'max_storage_mb'       => 'int',
			'price_per_month'      => 'int', // 最小単位の整数（例: JPYなら円）
			'is_active'            => 'bool'
		];
	}

	/** @return HasMany<User> */
	public function users(): HasMany
	{
		return $this->hasMany(User::class);
	}

	/* ---------- Scopes ---------- */

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

	/* ---------- Utils ---------- */

    /** 利用制限を配列で取得（ビュー層で使いやすい） */
    public function getLimitsAttribute(): array
    {
        return [
            'max_apps'            => $this->max_apps,
            'max_logs_per_app'    => $this->max_logs_per_app,
            'max_storage_mb'      => $this->max_storage_mb,
            'max_log_tags'        => $this->max_log_tags,
            'max_log_tag_length'  => $this->max_log_tag_length,
            'max_log_text_length' => $this->max_log_text_length,
        ];
    }

}
