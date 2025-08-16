<?php

/**
 * PullLog Currency Model
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

/**
 * @property string $code
 * @property string $name
 * @property string|null $symbol
 * @property string|null $symbol_native
 * @property int $minor_unit
 * @property float $rounding
 * @property string|null $name_plural
 *
 * @usage:
 * ```php
 * // 通貨コードで1件取得
 * $currency = Currency::byCode('JPY')->firstOrFail();
 *
 * // 複数通貨コードで取得
 * $currencies = Currency::byCodes(['USD', 'EUR'])->get();
 *
 * // 人気通貨を先頭に並べる
 * $popularCurrencies = Currency::popularFirst()->get();
 * 
 * // 通貨変換・整形（最小単位 <-> 十進）
 * $minor   = $app->toMinor('123.45');        // -> 12345
 * $decimal = $app->fromMinor(12345);         // -> "123.45"
 * $label   = $app->formatMinorAmount(12345); // -> "US$123.45" 等
 * 
 * // 金額ラベル（Currency 単体で）
 * $usd = Currency::byCode('USD')->first();
 * $label = $usd?->formatMinor(1999); // -> "US$19.99"
 * $step  = $usd?->step_size;         // -> 10.0
 * ```
 */
class Currency extends Model
{
    protected $table = 'currencies';
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = true;

    protected $fillable = [
        'code', 'name', 'symbol', 'symbol_native',
        'minor_unit', 'rounding', 'name_plural',
    ];

    protected function casts(): array
    {
        return [
            'minor_unit' => 'int',
            'rounding'   => 'float',
        ];
    }

    /** @return HasMany<App> */
    public function apps(): HasMany
    {
        return $this->hasMany(App::class, 'currency_code', 'code');
    }

    /* ========= Scopes ========= */

    public function scopeByCode(Builder $q, string $code): Builder
    {
        return $q->where('code', strtoupper($code));
    }

    public function scopeByCodes(Builder $q, array $codes): Builder
    {
        $codes = array_map(fn($c) => strtoupper((string)$c), $codes);
        return $q->whereIn('code', $codes);
    }

    /** よく使う通貨（UIのプルダウン先頭などに） */
    public function scopePopularFirst(Builder $q): Builder
    {
        $popular = ['USD','EUR','JPY','GBP','CNY','KRW'];
        return $q->orderByRaw(
            "CASE WHEN code IN ('".implode("','", $popular)."') THEN 0 ELSE 1 END, code"
        );
    }

    /** 名前・コード検索 */
    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $t = trim((string)$term);
        if ($t === '') return $q;
        return $q->where(function($w) use ($t) {
            $w->where('code', 'ILIKE', "%{$t}%")
              ->orWhere('name', 'ILIKE', "%{$t}%");
        });
    }

    /* ========= Utilities ========= */

    /** 十進文字列 → 最小単位整数（小数点や桁不足を安全に処理） */
    public static function decimalStringToMinorStatic(string $decimal, int $minor): int
    {
        $s = trim($decimal);
        if ($s === '') return 0;
        $neg = false;
        if ($s[0] === '-') { $neg = true; $s = substr($s, 1); }

        // 正規化
        if (!str_contains($s, '.')) {
            $int = $s; $frac = '';
        } else {
            [$int, $frac] = explode('.', $s, 2);
        }
        // 必要なだけ右側に0詰め
        $frac = substr(str_pad(preg_replace('/\D/', '', $frac), $minor, '0', STR_PAD_RIGHT), 0, $minor);
        $int  = preg_replace('/\D/', '', $int);
        $res  = ltrim($int.$frac, '0');
        if ($res === '') $res = '0';
        $val  = (int) $res;
        return $neg ? -$val : $val;
    }

    /** 最小単位整数 → 十進文字列（精度落とさない） */
    public static function minorToDecimalStringStatic(int $amount, int $minor): string
    {
        $s = (string)$amount;
        $neg = false;
        if (str_starts_with($s, '-')) { $neg = true; $s = substr($s, 1); }

        if ($minor <= 0) return $neg ? ('-'.$s) : $s;

        if (strlen($s) <= $minor) {
            $s = str_pad($s, $minor + 1, '0', STR_PAD_LEFT);
        }
        $idx = strlen($s) - $minor;
        $res = substr($s, 0, $idx) . '.' . substr($s, $idx);
        return $neg ? ('-'.$res) : $res;
    }

    /** 金額（最小単位）を通貨書式へ */
    public function formatMinor(int $amount, ?string $locale = null): string
    {
        $decimal = self::minorToDecimalStringStatic($amount, $this->minor_unit ?? 0);
        try {
            return Number::currency($decimal, $this->code, locale: $locale ?? (app()->getLocale() ?: 'en_US'));
        } catch (\Throwable) {
            return $decimal.' '.$this->code;
        }
    }

    /**
     * グラフのY軸の目盛幅（フロント util の簡易移植）
     * KRW/JPY/CNY/USD/EURプリセット → それ以外は 10^-minor * rounding を下限0.01で
     */
    public function getStepSizeAttribute(): float
    {
        $code = $this->code;
        if (in_array($code, ['KRW'], true)) return 5000.0;
        if (in_array($code, ['JPY'], true)) return 1000.0;
        if (in_array($code, ['CNY'], true)) return 50.0;
        if (in_array($code, ['USD','EUR'], true)) return 10.0;

        $minor = $this->minor_unit ?? 0;
        $round = $this->rounding ?? 1.0;
        $step  = pow(10, -$minor) * ($round ?: 1.0);
        if ($step < 1) $step = 1.0;
        if ($step < 0.01) $step = 0.01; // 念のため
        return (float)$step;
    }

}
