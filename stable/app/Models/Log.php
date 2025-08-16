<?php

/**
 * PullLog Log Model
 */

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * @property int $id
 * @property int $user_id
 * @property int $app_id
 * @property Carbon $log_date   			// DATE
 * @property int $total_pulls
 * @property int $discharge_items
 * @property int $expense_amount            // 最小単位の整数
 * @property array|null $drop_details
 * @property array|null $tags
 * @property string|null $free_text
 * @property array|null $images
 * @property array|null $tasks
 * @property-read string $expense_decimal   // 10^minor_unit で割った文字列
 * @property-read string $expense_formatted // ロケール付き通貨書式
 *
 * @usage:
 * ```php
 * // 取得：十進とフォーマット
 * $log = Log::with('app.currency')->forUser(3)->latest('log_date')->first();
 * $decimal  = $log->expense_decimal;     // "1234.56"
 * $display  = $log->expense_formatted;   // "US$1,234.56" など
 *
 * // 保存（十進→最小単位）
 * $app = App::with('currency')->findOrFail($appId);
 * $minor = $app->currency->minor_unit;   // 2 (USD)
 * $decimal = '12.34';
 * $amount = (int) str_replace('.', '', str_pad($decimal, strlen($decimal) + max($minor - (strlen($decimal) - (strrpos($decimal, '.') ?: strlen($decimal))), 0), '0'));
 * Log::create([
 *     'user_id' => $userId,
 *     'app_id' => $appId,
 *     'log_date' => today(),
 *     'total_pulls' => 10,
 *     'discharge_items' => 2,
 *     'expense_amount' => $amount,
 * ]);
 * ```
 */
class Log extends Model
{
	protected $table = 'logs';

	// id は BIGSERIAL（自動採番）。ただし更新時は user_id もキーに含めたいので setKeysForSaveQuery を上書き
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'user_id',
		'app_id',
		'log_date',
		'total_pulls',
		'discharge_items',
		'expense_amount',
		'drop_details',
		'tags',
		'free_text',
		'images',
		'tasks'
	];

    protected function casts(): array
	{
		return [
			'id' 			  => 'int',
			'user_id' 		  => 'int',
			'app_id' 		  => 'int',
			'log_date' 		  => 'date',
			'total_pulls' 	  => 'int',
			'discharge_items' => 'int',
			'expense_amount'  => 'int',
			'drop_details'    => 'array',
			'tags'            => 'array',
			'images'          => 'array',
			'tasks'           => 'array',
		];
	}

	/** 既定で通貨参照までまとめて取る（必要なければ外してOK） */
    protected $with = ['app.currency'];

	/** @return BelongsTo<App, Log> */
    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class, 'app_id', 'id');
    }

	/** @return BelongsTo<User, Log> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

	/**
     * 更新/削除時の WHERE に user_id も含める（複合キー対策）
     * Eloquent は単一PK前提なので、この上書きで実務的に運用
     *
     * @param Builder|QueryBuilder $query
     * @return Builder|QueryBuilder
     */
    protected function setKeysForSaveQuery($query)
    {
        // id は通常どおり
        $query->where('id', '=', $this->getAttribute('id'));

        // user_id はオリジナル値（変更があった場合でも）でヒットさせる
        $userId = $this->getOriginal('user_id') ?? $this->getAttribute('user_id');
        if ($userId === null) {
            // 安全のため、user_id が無い状態での更新は拒否したい時は例外
            throw new \LogicException('user_id is required for updating logs.');
        }
        return $query->where('user_id', '=', $userId);
    }

	/** 金額（十進）を文字列で返す。precisionを保ちたいので string */
    public function getExpenseDecimalAttribute(): string
    {
        $minor = $this->app?->currency?->minor_unit ?? 0;
        if ($minor <= 0) {
            return (string) $this->expense_amount;
        }
        // 小数点位置を手動で挿入（浮動小数演算を避ける）
        $s = (string) $this->expense_amount;
        $neg = false;
        if (str_starts_with($s, '-')) {
            $neg = true;
            $s = substr($s, 1);
        }
        if (strlen($s) <= $minor) {
            $s = str_pad($s, $minor + 1, '0', STR_PAD_LEFT);
        }
        $idx = strlen($s) - $minor;
        $res = substr($s, 0, $idx) . '.' . substr($s, $idx);
        return $neg ? ('-' . $res) : $res;
    }

	/** Intl書式での通貨表示（ロケールは呼び出し側で上書き可） */
    public function getExpenseFormattedAttribute(): string
    {
        $code = $this->app?->currency?->code ?? 'USD';
        $locale = app()->getLocale() ?: 'en_US';

        // PHP 8.4+ の Number::currency を使って安全に
        try {
            // $this->expense_decimal は string。Number::currency は float|int|string 受け付け。
            return Number::currency($this->expense_decimal, $code, locale: $locale);
        } catch (\Throwable $e) {
            return $this->expense_decimal . ' ' . $code;
        }
    }

	/* ========= スコープ ========= */

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    public function scopeForApp(Builder $q, int $appId): Builder
    {
        return $q->where('app_id', $appId);
    }

    public function scopeDateBetween(Builder $q, string|\DateTimeInterface $from, string|\DateTimeInterface $to): Builder
    {
        return $q->whereBetween('log_date', [$from, $to]);
    }

}
