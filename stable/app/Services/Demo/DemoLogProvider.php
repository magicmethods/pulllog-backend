<?php

namespace App\Services\Demo;

use Carbon\Carbon;
use Illuminate\Support\Collection;


class DemoLogProvider
{
    /**
     * 直近N日分の合成ログを返す（アプリ単位）
     */
    public function generateRecentDailyLogs(int $appId = 0, ?Carbon $to = null, int $days = 30): Collection
    {
        $end = ($to ?? Carbon::today())->clone()->startOfDay();
        $start = $end->clone()->subDays($days - 1);
        return $this->generateRange($appId, $start, $end);
    }

    /**
     * 指定期間の合成ログを返す（両端含む）
     */
    public function generateRange(int $appId, Carbon $from, Carbon $to): Collection
    {
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $cursor = $from->clone();
        $rows = [];

        // 周期性（ガチャ更新周期の想定：14日ごと）をアプリごとに位相ずらし
        $bannerPhaseOffset = $this->pseudoUniform((string)$appId.'-phase', 0, 6); // 0〜6日

        // pity（天井）っぽい挙動のためのカウンタ（決定論的だが日付・appIdで再現可能）
        $sinceLastRare = 0;
        $pitySoftCap   = 60;  // 緩い救済（徐々にレア率が上がる開始点）
        $pityHardCap   = 90;  // ほぼ確定寄り

        while ($cursor->lte($to)) {
            $seed = $this->makeSeed($appId, $cursor);

            // --- 1) ベースの引き回数（平日/週末・バナー開始・月末付近でブースト）---
            $basePull = $this->pseudoUniform($seed.'-base', 5, 30); // 通常日
            $dow = (int)$cursor->dayOfWeekIso; // 1=Mon ... 7=Sun

            // 週末ブースト（軽め）
            if ($dow >= 6) {
                $basePull = (int) round($basePull * 1.2);
            }

            // バナー周期（14日ごと + 位相ずらし）：開始日±2日は強めに
            $daysFromBanner = ($cursor->diffInDays(Carbon::parse('2024-01-01')->addDays($bannerPhaseOffset)) % 14);
            if ($daysFromBanner <= 2) {
                $basePull = (int) round($basePull * 1.6);
            }

            // 月末/翌月頭（石配布やピック切替想定）で微ブースト
            $dom = (int)$cursor->day;
            if ($dom >= 28 || $dom <= 2) {
                $basePull = (int) round($basePull * 1.15);
            }

            // 0〜少数の日もたまに（全く引かない日）
            if ($this->pseudoUniform($seed.'-zeroDay', 0, 9) === 0) {
                $basePull = $this->pseudoUniform($seed.'-tiny', 0, 3);
            }

            // 上限・下限クリップ
            $pullCount = max(0, min(80, $basePull));

            // --- 2) レア排出数（基本2〜5%を中心に、pityを織り込み） ---
            // ベースレア率：2〜5%を中心（ゲームにより差異。必要なら上下調整）
            $baseRareRate = $this->pseudoUniformFloat($seed.'-rr', 0.02, 0.05);

            // pity影響：連続でレア無しが続くほどレア率を足し上げる
            // ※ これは合成のための擬似pity。実装側のpity仕様とは無関係
            $pityBonus = 0.0;
            if ($sinceLastRare > $pitySoftCap) {
                $pityBonus += min(0.15, ($sinceLastRare - $pitySoftCap) * 0.002); // 上限+15%
            }

            $rareRate = min(0.25, $baseRareRate + $pityBonus); // 上限25%（異常値防止）

            // レア数は二項分布風に丸める（pullが少ない日は0が出やすい）
            $expectedRare = $pullCount * $rareRate;

            // 期待値±揺らぎ（Poisson近似的な揺らぎ）
            $jitter = $this->pseudoUniformFloat($seed.'-rj', -0.8, 0.8);
            $rareCount = (int) round(max(0.0, $expectedRare + $jitter));

            // ハードpity：極端にレア無しが続いたら強制的に1以上へ
            if ($pullCount > 0) {
                if ($sinceLastRare >= $pityHardCap) {
                    $rareCount = max(1, $rareCount);
                }
                $sinceLastRare = ($rareCount > 0) ? 0 : ($sinceLastRare + $pullCount);
            } else {
                // 引いていない日はカウントを進めない
                $sinceLastRare = $sinceLastRare;
            }

            // --- 3) 課金額（expense_amount） ---
            // ① 無料石・配布日（0円）：平日の一部＋ランダム
            $isFreeDay = ($dow <= 5 && $this->pseudoUniform($seed.'-free', 0, 7) === 0)
                      || ($this->pseudoUniform($seed.'-free2', 0, 19) === 0);

            // ② 購入単価（ガチャ券/石のレート差を擬似的に）：80〜200（任意通貨単位）
            $unit = $this->pseudoUniformFloat($seed.'-unit', 80.0, 200.0);

            // ③ pullCountに応じたスケール。イベント/バナー開始付近は若干上振れ
            $expense = $isFreeDay ? 0.0 : ($unit * $pullCount);

            if ($daysFromBanner <= 2) {
                $expense *= 1.15;
            }
            if ($rareCount > 0) {
                // レアを引けた日は「追い課金が止まる」or「勢いで少し足す」をランダムで
                $expense *= ( $this->pseudoUniform($seed.'-afterRareAdd', 0, 1) === 0 ? 0.95 : 1.05 );
            }

            // ④ 微揺らぎ
            $expense *= (1.0 + $this->pseudoUniformFloat($seed.'-em', -0.05, 0.05));

            // 整数化（小数不要の場合。必要なら round(..., 2) に変更）
            $expenseAmount = (int) max(0, round($expense));

            // --- 4) free_text（たまにメモを付ける） ---
            $freeText = null;
            $memoRoll = $this->pseudoUniform($seed.'-memo', 0, 9);
            if ($memoRoll === 0 && $pullCount > 0) {
                if ($daysFromBanner <= 1) {
                    $freeText = 'New gacha begins';
                } elseif ($rareCount > 0) {
                    $freeText = 'Awesome drop!';
                } elseif ($isFreeDay) {
                    $freeText = 'I used the distributed stones to spin';
                } else {
                    $freeText = 'I\'ll turn a little to see how it goes';
                }
            }

            $rows[] = [
                'date'             => $cursor->toDateString(), // 'YYYY-MM-DD'
                'total_pulls'      => $pullCount,
                'discharge_items'  => $rareCount,
                'expense_amount'   => $expenseAmount,
                'free_text'        => $freeText,
                'app_id'           => $appId,
            ];

            $cursor->addDay();
        }

        return collect($rows);
    }

    private function makeSeed(int $appId, Carbon $date): string
    {
        return $appId.':'.$date->format('Ymd');
    }

    /** 疑似乱数（整数） */
    private function pseudoUniform(string $seed, int $min, int $max): int
    {
        $hash = crc32($seed);
        $val  = $hash % ($max - $min + 1);
        return $min + (int)$val;
    }

    /** 疑似乱数（浮動小数） */
    private function pseudoUniformFloat(string $seed, float $min, float $max): float
    {
        $hash  = crc32($seed);
        $ratio = ($hash & 0xffff) / 0xffff; // 0〜1
        return $min + ($max - $min) * $ratio;
    }
}
