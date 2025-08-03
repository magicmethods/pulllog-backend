<?php

/**
 * PullLog StatsCache Model
 * This model has been merged that created by reliese model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class StatsCache
 * 
 * @property int|null $user_id
 * @property string $cache_key
 * @property string $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class StatsCache extends Model
{
	protected $table = 'stats_cache';
	protected $primaryKey = 'cache_key';
	public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
	protected $fillable = [
		'cache_key',
		'user_id',
		'value'
	];

	/**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
	{
		return [
			'user_id' => 'int'
		];
	}

}
