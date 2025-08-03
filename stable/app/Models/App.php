<?php

/**
 * PullLog App Model
 * This model has been merged that created by reliese model.
 */

namespace App\Models;

use App\Casts\DefinitionCast;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class App
 * 
 * @property int $id
 * @property string $app_key
 * @property string $name
 * @property string|null $url
 * @property string|null $description
 * @property string|null $currency_unit
 * @property string $date_update_time
 * @property bool $sync_update_time
 * @property bool $pity_system
 * @property int $guarantee_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property array|null $rarity_defs
 * @property array|null $marker_defs
 * @property array|null $task_defs
 * 
 * @property Collection|User[] $users
 * @property Collection|LogsP0[] $logs_p0s
 * @property Collection|LogsP1[] $logs_p1s
 * @property Collection|LogsP2[] $logs_p2s
 * @property Collection|LogsP3[] $logs_p3s
 * @property Collection|LogsP4[] $logs_p4s
 * @property Collection|LogsP5[] $logs_p5s
 * @property Collection|LogsP6[] $logs_p6s
 * @property Collection|LogsP7[] $logs_p7s
 * @property Collection|LogsP8[] $logs_p8s
 * @property Collection|LogsP9[] $logs_p9s
 *
 * @package App\Models
 */
class App extends Model
{
	protected $table = 'apps';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
	protected $fillable = [
		'app_key',
		'name',
		'url',
		'description',
		'currency_unit',
		'date_update_time',
		'sync_update_time',
		'pity_system',
		'guarantee_count',
		'rarity_defs',
		'marker_defs',
		'task_defs'
	];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
	{
		return [
			'sync_update_time' => 'bool',
			'pity_system' => 'bool',
			'guarantee_count' => 'int',
			'rarity_defs' => 'array',
			'marker_defs' => 'array',
			'task_defs' => 'array',
		];
	}

	public function users()
	{
		return $this->belongsToMany(User::class, 'user_apps', 'app_id', 'user_id')
					->withPivot('id')
					->withTimestamps();
	}

	public function logs_p0s()
	{
		return $this->hasMany(LogsP0::class);
	}

	public function logs_p1s()
	{
		return $this->hasMany(LogsP1::class);
	}

	public function logs_p2s()
	{
		return $this->hasMany(LogsP2::class);
	}

	public function logs_p3s()
	{
		return $this->hasMany(LogsP3::class);
	}

	public function logs_p4s()
	{
		return $this->hasMany(LogsP4::class);
	}

	public function logs_p5s()
	{
		return $this->hasMany(LogsP5::class);
	}

	public function logs_p6s()
	{
		return $this->hasMany(LogsP6::class);
	}

	public function logs_p7s()
	{
		return $this->hasMany(LogsP7::class);
	}

	public function logs_p8s()
	{
		return $this->hasMany(LogsP8::class);
	}

	public function logs_p9s()
	{
		return $this->hasMany(LogsP9::class);
	}
}
