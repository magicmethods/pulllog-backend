<?php

/**
 * PullLog AuthToken Model
 * This model has been merged that created by reliese model.
 */

namespace App\Models;

use App\Casts\TokenTypeCast;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class AuthToken
 * 
 * @property int $id
 * @property int $user_id
 * @property string $token
 * @property TokenType $type
 * @property string|null $code
 * @property bool $is_used
 * @property string|null $ip
 * @property string|null $ua
 * @property Carbon $expires_at
 * @property Carbon $created_at
 * 
 * @property User $user
 *
 * @package App\Models
 */
class AuthToken extends Model
{
	protected $table = 'auth_tokens';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
	protected $fillable = [
		'user_id',
		'token',
		'type',
		'code',
		'is_used',
		'ip',
		'ua',
		'expires_at'
	];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
	protected $hidden = [
		'token'
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
			'type' => TokenTypeCast::class,
			'is_used' => 'bool',
			'expires_at' => 'datetime'
		];
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}
}
