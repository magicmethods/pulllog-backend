<?php

/**
 * PullLog UserApp Model
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class UserApp
 * 
 * @property int $id
 * @property int $user_id
 * @property int $app_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserApp extends Model
{
	protected $table = 'user_apps';
	// id(PK)はDB側でauto increment
    public $incrementing = true;
    protected $keyType = 'int';

	protected $fillable = [
		'user_id',
		'app_id'
	];

    protected function casts(): array
	{
		return [
			'user_id' 	 => 'int',
			'app_id' 	 => 'int',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
		];
	}

	/** @return BelongsTo<User,UserApp> */
	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}

	/** @return BelongsTo<App,UserApp> */
	public function app(): BelongsTo
	{
		return $this->belongsTo(App::class);
	}

    /* ---------- Scopes ---------- */

    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function scopeForApp($q, int $appId)
    {
        return $q->where('app_id', $appId);
    }

	/* ---------- Utilities ---------- */

    /**
     * (user_id, app_id) の一意制約を前提に冪等リンクを確立
     * 既存があれば updated_at だけ更新
     */
    public static function ensureLink(int $userId, int $appId): self
    {
        return static::updateOrCreate(
            ['user_id' => $userId, 'app_id' => $appId],
            [] // 触る属性がなければ空でOK（updated_atは自動更新）
        );
    }

}
