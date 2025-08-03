<?php

/**
 * PullLog UserSession Model
 * This model has been merged that created by reliese model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class UserSession
 * 
 * @property string $csrf_token
 * @property int $user_id
 * @property string $email
 * @property Carbon $created_at
 * @property Carbon $expires_at
 * 
 * @property User $user
 *
 * @package App\Models
 */
class UserSession extends Model
{
	protected $table = 'user_sessions';
	protected $primaryKey = 'csrf_token';
	public $incrementing = false;
	public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
	protected $fillable = [
        'csrf_token',
		'user_id',
		'email',
        'created_at',
		'expires_at'
	];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
	protected $hidden = [
		'csrf_token'
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
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

	public function user()
	{
		return $this->belongsTo(User::class);
	}
}
