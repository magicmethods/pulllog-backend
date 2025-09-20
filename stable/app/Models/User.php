<?php

/**
 * PullLog User Model
 */

namespace App\Models;

use App\Casts\ThemeCast;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;

/**
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
 * @usage:
 * ```php
 * // User ↁE紐づくアプリの一覧�E�Eivot含む�E�E * $user = User::with(['userApps.app'])->findOrFail(3);
 *
 * // User ↁEログ�E�整数の最小単位！E * $logs = $user->logs()->latest('log_date')->limit(30)->get();
 *
 * // User ↁEログ�E�Eecimal/通貨コード付きのビュー�E�E * $moneyLogs = $user->moneyLogs()->forApp(7)->dateBetween('2025-08-01', '2025-08-31')->get();
 *
 * // App ↁEこ�Eアプリに紐づくユーザーピ�EチE��
 * $app = App::with('userApps.user')->findOrFail(7);
 *
 * // 冪等リンク
 * $user->linkApp($app->id);           // user_apps upsert 相彁E * $has = $user->hasApp($app->id);     // true/false
 * ```
 */
class User extends Authenticatable
{
	/** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

	protected $table = 'users';

    /**
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
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

	protected function casts(): array
    {
        return [
            'password' 			=> 'hashed',
			'roles' 			=> 'array',
			'plan_id' 			=> 'int',
			'plan_expiration' 	=> 'datetime',
			'theme' 			=> ThemeCast::class,
			'last_login' 		=> 'datetime',
			'is_deleted' 		=> 'bool',
			'is_verified' 		=> 'bool',
            'email_verified_at' => 'datetime',
			'unread_notices' 	=> 'array',
        ];
    }

	/* ========= リレーション ========= */

	/** @return BelongsTo<Plan,User> */
	public function plan(): BelongsTo
	{
		return $this->belongsTo(Plan::class);
	}

	/** @return HasMany<AuthToken> */
	public function auth_tokens(): HasMany
	{
		return $this->hasMany(AuthToken::class);
	}

	/**
     * 多対多（既存�E通り�E�E     * @return BelongsToMany<App>
     */
	public function apps(): BelongsToMany
	{
		return $this->belongsToMany(App::class, 'user_apps', 'user_id', 'app_id')
			->withPivot('id')
			->withTimestamps();
	}

	/** @return HasMany<UserSession> */
	public function user_sessions(): HasMany
	{
		return $this->hasMany(UserSession::class);
	}

	/**
	 * 親 logs チE�Eブルへ一本化（パーチE��ションはDBが�E動ルーチE��ング�E�E     * @return HasMany<Log>
     */
	public function logs(): HasMany
	{
		return $this->hasMany(Log::class, 'user_id', 'id');
	}

	/**
	 * ビュー logs_with_money�E�読み取り専用モチE���E�E     * @return HasMany<LogWithMoney>
     */
    public function moneyLogs(): HasMany
    {
        return $this->hasMany(LogWithMoney::class, 'user_id', 'id');
    }

	/**
	 * ピ�EチE��明示�E�user_apps
     * @return HasMany<UserApp>
     */
    public function userApps(): HasMany
    {
        return $this->hasMany(UserApp::class, 'user_id', 'id');
    }

    /** @return HasMany<UserFilter> */
    public function userFilters(): HasMany
    {
        return $this->hasMany(UserFilter::class, 'user_id', 'id');
    }

	/* ========= スコーチE========= */

    public function scopeActive($q)
    {
        return $q->where('is_deleted', false);
    }

    public function scopeVerified($q)
    {
        return $q->where('is_verified', true);
    }

	/* ========= ユーチE��リチE�� ========= */

    /** 持E��アプリと紐付いてぁE��ぁE*/
    public function hasApp(int $appId): bool
    {
        // user_apps のユニ�Eク制紁E��Eser_id, app_id�E�前提で高送E        return $this->userApps()->where('app_id', $appId)->exists();
    }

    /** アプリと冪等にリンク�E�無ければ作�E、あれ�E何もしなぁE��E*/
    public function linkApp(int $appId): UserApp
    {
        return UserApp::ensureLink($this->id, $appId);
    }

}



