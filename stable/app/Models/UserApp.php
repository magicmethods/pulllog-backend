<?php

/**
 * PullLog UserApp Model
 * This model has been merged that created by reliese model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class UserApp
 * 
 * @property int $id
 * @property int $user_id
 * @property int $app_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property User $user
 * @property App $app
 *
 * @package App\Models
 */
class UserApp extends Model
{
	protected $table = 'user_apps';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
	protected $fillable = [
		'user_id',
		'app_id'
	];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
	{
		return [
			'user_id' => 'int',
			'app_id' => 'int'
		];
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}

	public function app()
	{
		return $this->belongsTo(App::class);
	}
}
