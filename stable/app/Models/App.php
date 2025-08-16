<?php

/**
 * PullLog App Model
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

/**
 * @property int $id
 * @property string $app_key
 * @property string $name
 * @property string|null $url
 * @property string|null $description
 * @property string $currency_code   // ← currency_unit 廃止
 * @property string $date_update_time
 * @property bool $sync_update_time
 * @property bool $pity_system
 * @property int $guarantee_count
 * @property array|null $rarity_defs
 * @property array|null $marker_defs
 * @property array|null $task_defs
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @usage:
 * ```php
 * // ユーザーのアプリ一覧を検索 + 通貨ロード + 新しい順
 * $apps = App::forUser($userId)
 *     ->search($request->q)
 *     ->withCurrency()
 *     ->recent()
 *     ->paginate(20);
 *
 * // app_key で1件取得
 * $app = App::byKey($appKey)->withCurrency()->firstOrFail();
 *
 * // アプリとユーザーを冪等リンク
 * $app->ensureLinkedTo($userId);
 * ```
 */
class App extends Model
{
	protected $table = 'apps';

	protected $fillable = [
		'app_key',
		'name',
		'url',
		'description',
		'currency_code',
		'date_update_time',
		'sync_update_time',
		'pity_system',
		'guarantee_count',
		'rarity_defs',
		'marker_defs',
		'task_defs'
	];

    protected function casts(): array
	{
		return [
			'sync_update_time' => 'bool',
			'pity_system' 	   => 'bool',
			'guarantee_count'  => 'int',
			'rarity_defs'      => 'array',
			'marker_defs'      => 'array',
			'task_defs'        => 'array',
		];
	}

	/** @return BelongsToMany<User> */
	public function users(): BelongsToMany
	{
		return $this->belongsToMany(User::class, 'user_apps', 'app_id', 'user_id')
			->withPivot('id')
			->withTimestamps();
	}

	/** @return HasMany<Log> */
	public function logs(): HasMany
	{
		return $this->hasMany(Log::class, 'app_id', 'id');
	}

	/** @return BelongsTo<Currency, App> */
	public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

	// UserApp（ピボットの明示的リレーション）
	public function userApps(): HasMany
	{
    	return $this->hasMany(UserApp::class, 'app_id', 'id');
	}

	// LogWithMoney（ビュー）
	public function moneyLogs(): HasMany
	{
    	return $this->hasMany(LogWithMoney::class, 'app_id', 'id');
	}

	/* ========= Scopes ========= */

    /** ユーザーが所有するアプリに限定（user_apps 経由） */
    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->whereHas('userApps', fn($qq) => $qq->where('user_id', $userId));
    }

    /** app_key で特定 */
    public function scopeByKey(Builder $q, string $appKey): Builder
    {
        return $q->where('app_key', $appKey);
    }

    /** 名前・説明の簡易検索（Postgres想定の ILIKE） */
    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $t = trim((string)$term);
        if ($t === '') return $q;
        return $q->where(function ($w) use ($t) {
            $w->where('name', 'ILIKE', "%{$t}%")
              ->orWhere('description', 'ILIKE', "%{$t}%");
        });
    }

    /** 通貨を常に eager load */
    public function scopeWithCurrency(Builder $q): Builder
    {
        return $q->with('currency');
    }

    /** 更新の新しい順で並べる */
    public function scopeRecent(Builder $q): Builder
    {
        return $q->orderByDesc('updated_at');
    }

    /* ========= Utilities ========= */

    /** 指定ユーザーに冪等リンク（存在しなければ作成） */
    public function ensureLinkedTo(int $userId): UserApp
    {
        return UserApp::ensureLink($userId, $this->id);
    }

    /** 指定ユーザーとのリンク有無 */
    public function isLinkedTo(int $userId): bool
    {
        return $this->userApps()->where('user_id', $userId)->exists();
    }

    /** 指定ユーザーとのリンク解除（削除数を返す） */
    public function detachFrom(int $userId): int
    {
        return $this->userApps()->where('user_id', $userId)->delete();
    }

    /** 通貨の少数桁（なければ0） */
    public function getCurrencyMinorUnitAttribute(): int
    {
        return $this->currency?->minor_unit ?? 0;
    }

    /** 最小単位の整数 -> 通貨書式（例: 12345 -> "$123.45"） */
    public function formatMinorAmount(int $amount, ?string $locale = null): string
    {
        $code  = $this->currency?->code ?? 'USD';
        $minor = $this->currency?->minor_unit ?? 0;

        // 小数文字列に直す（精度維持）
        $decimal = Currency::minorToDecimalStringStatic($amount, $minor);

        try {
            return Number::currency($decimal, $code, locale: $locale ?? (app()->getLocale() ?: 'en_US'));
        } catch (\Throwable) {
            return $decimal.' '.$code;
        }
    }

    /** 十進文字列/数値 -> 最小単位の整数（例: "123.45" -> 12345） */
    public function toMinor(string|int|float $decimal): int
    {
        $minor = $this->currency?->minor_unit ?? 0;
        return Currency::decimalStringToMinorStatic((string)$decimal, $minor);
    }

    /** 最小単位の整数 -> 十進文字列（例: 12345 -> "123.45"） */
    public function fromMinor(int $amount): string
    {
        $minor = $this->currency?->minor_unit ?? 0;
        return Currency::minorToDecimalStringStatic($amount, $minor);
    }

}
