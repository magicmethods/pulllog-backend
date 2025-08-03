<?php

/**
 * PullLog User Model
 * This model has been merged that created by reliese model.
 */

namespace App\Models;

use App\Casts\ThemeCast;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class User
 * 
 * @property int $id
 * @property string $email
 * @property string $password
 * @property string $name
 * @property string|null $avatar_url
 * @property array $roles
 * @property int $plan_id
 * @property Carbon $plan_expiration
 * @property string $language
 * @property Theme $theme
 * @property string $home_page
 * @property Carbon|null $last_login
 * @property string|null $last_login_ip
 * @property string|null $last_login_ua
 * @property bool $is_deleted
 * @property bool $is_verified
 * @property string|null $remember_token
 * @property array|null $unread_notices
 * @property Carbon|null $email_verified_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property Plan $plan
 * @property Collection|AuthToken[] $auth_tokens
 * @property Collection|App[] $apps
 * @property Collection|UserSession[] $user_sessions
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
class User extends Authenticatable
{
	/** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

	protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
	protected $fillable = [
		'email',
		'password',
		'name',
		'avatar_url',
		'roles',
		'plan_id',
		'plan_expiration',
		'language',
		'theme',
		'home_page',
		'last_login',
		'last_login_ip',
		'last_login_ua',
		'is_deleted',
		'is_verified',
		'remember_token',
		'unread_notices',
		'email_verified_at',
	];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
			'roles' => 'array',
			'plan_id' => 'int',
			'plan_expiration' => 'datetime',
			'theme' => ThemeCast::class,
			'last_login' => 'datetime',
			'is_deleted' => 'bool',
			'is_verified' => 'bool',
            'email_verified_at' => 'datetime',
			'unread_notices' => 'array',
        ];
    }

	public function plan()
	{
		return $this->belongsTo(Plan::class);
	}

	public function auth_tokens()
	{
		return $this->hasMany(AuthToken::class);
	}

	public function apps()
	{
		return $this->belongsToMany(App::class, 'user_apps', 'user_id', 'app_id')
					->withPivot('id')
					->withTimestamps();
	}

	public function user_sessions()
	{
		return $this->hasMany(UserSession::class);
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
