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
 * // User 竊・邏舌▼縺上い繝励Μ縺ｮ荳隕ｧ・・ivot蜷ｫ繧・・ * $user = User::with(['userApps.app'])->findOrFail(3);
 *
 * // User 竊・繝ｭ繧ｰ・域紛謨ｰ縺ｮ譛蟆丞腰菴搾ｼ・ * $logs = $user->logs()->latest('log_date')->limit(30)->get();
 *
 * // User 竊・繝ｭ繧ｰ・・ecimal/騾夊ｲｨ繧ｳ繝ｼ繝我ｻ倥″縺ｮ繝薙Η繝ｼ・・ * $moneyLogs = $user->moneyLogs()->forApp(7)->dateBetween('2025-08-01', '2025-08-31')->get();
 *
 * // App 竊・縺薙・繧｢繝励Μ縺ｫ邏舌▼縺上Θ繝ｼ繧ｶ繝ｼ繝斐・繝・ヨ
 * $app = App::with('userApps.user')->findOrFail(7);
 *
 * // 蜀ｪ遲峨Μ繝ｳ繧ｯ
 * $user->linkApp($app->id);           // user_apps upsert 逶ｸ蠖・ * $has = $user->hasApp($app->id);     // true/false
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

	/* ========= 繝ｪ繝ｬ繝ｼ繧ｷ繝ｧ繝ｳ ========= */

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
     * 螟壼ｯｾ螟夲ｼ域里蟄倥・騾壹ｊ・・     * @return BelongsToMany<App>
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
	 * 隕ｪ logs 繝・・繝悶Ν縺ｸ荳譛ｬ蛹厄ｼ医ヱ繝ｼ繝・ぅ繧ｷ繝ｧ繝ｳ縺ｯDB縺瑚・蜍輔Ν繝ｼ繝・ぅ繝ｳ繧ｰ・・     * @return HasMany<Log>
     */
	public function logs(): HasMany
	{
		return $this->hasMany(Log::class, 'user_id', 'id');
	}

	/**
	 * 繝薙Η繝ｼ logs_with_money・郁ｪｭ縺ｿ蜿悶ｊ蟆ら畑繝｢繝・Ν・・     * @return HasMany<LogWithMoney>
     */
    public function moneyLogs(): HasMany
    {
        return $this->hasMany(LogWithMoney::class, 'user_id', 'id');
    }

	/**
	 * 繝斐・繝・ヨ譏守､ｺ・嗽ser_apps
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

	/* ========= 繧ｹ繧ｳ繝ｼ繝・========= */

    public function scopeActive($q)
    {
        return $q->where('is_deleted', false);
    }

    public function scopeVerified($q)
    {
        return $q->where('is_verified', true);
    }

	/* ========= 繝ｦ繝ｼ繝・ぅ繝ｪ繝・ぅ ========= */

    /** 謖・ｮ壹い繝励Μ縺ｨ邏蝉ｻ倥＞縺ｦ縺・ｋ縺・*/
    public function hasApp(int $appId): bool
    {
        // user_apps 縺ｮ繝ｦ繝九・繧ｯ蛻ｶ邏・ｼ・ser_id, app_id・牙燕謠舌〒鬮倬・        return $this->userApps()->where('app_id', $appId)->exists();
    }

    /** 繧｢繝励Μ縺ｨ蜀ｪ遲峨↓繝ｪ繝ｳ繧ｯ・育┌縺代ｌ縺ｰ菴懈・縲√≠繧後・菴輔ｂ縺励↑縺・ｼ・*/
    public function linkApp(int $appId): UserApp
    {
        return UserApp::ensureLink($this->id, $appId);
    }

}



