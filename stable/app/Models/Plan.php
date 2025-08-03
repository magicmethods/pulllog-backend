<?php

/**
 * PullLog Plan Model
 * This model has been merged that created by reliese model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Plan
 * 
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
 * @property Collection|User[] $users
 *
 * @package App\Models
 */
class Plan extends Model
{
	protected $table = 'plans';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
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
		'is_active'
	];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
	{
		return [
			'max_apps' => 'int',
			'max_app_name_length' => 'int',
			'max_app_desc_length' => 'int',
			'max_log_tags' => 'int',
			'max_log_tag_length' => 'int',
			'max_log_text_length' => 'int',
			'max_logs_per_app' => 'int',
			'max_storage_mb' => 'int',
			'price_per_month' => 'int',
			'is_active' => 'bool'
		];
	}

	public function users()
	{
		return $this->hasMany(User::class);
	}
}
