<?php

/**
 * PullLog Log Model
 * This model has been merged that created by reliese model.
 */

namespace App\Models;

//use App\Casts\DropArrayCast;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Log
 * 
 * @property int $id
 * @property int $user_id
 * @property int $app_id
 * @property Carbon $log_date
 * @property int $total_pulls
 * @property int $discharge_items
 * @property int|null $expense
 * @property array|null $drop_details
 * @property array|null $tags
 * @property string|null $free_text
 * @property array|null $images
 * @property array|null $tasks
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @package App\Models
 */
class Log extends Model
{
	protected $table = 'logs';
	public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
	protected $fillable = [
		'id',
		'user_id',
		'app_id',
		'log_date',
		'total_pulls',
		'discharge_items',
		'expense',
		'drop_details',
		'tags',
		'free_text',
		'images',
		'tasks'
	];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
	{
		return [
			'id' => 'int',
			'user_id' => 'int',
			'app_id' => 'int',
			'log_date' => 'datetime',
			'total_pulls' => 'int',
			'discharge_items' => 'int',
			'expense' => 'int',
			'drop_details' => 'array',
			'tags' => 'array',
			'images' => 'array',
			'tasks' => 'array',
		];
	}

}
