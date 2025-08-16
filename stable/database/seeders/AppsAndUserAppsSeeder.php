<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SplFileObject;

class AppsAndUserAppsSeeder extends Seeder
{
    private string $csvPath;

    public function __construct()
    {
        $this->csvPath = base_path('database/seeders/data/apps.csv');
    }

    public function run(): void
    {
        if (!is_file($this->csvPath)) {
            throw new \RuntimeException("apps.csv not found at: {$this->csvPath}");
        }

        DB::transaction(function () {
            $apps = $this->readCsv();
            if (empty($apps)) {
                return;
            }

            // apps へUPSERT
            DB::table('apps')->upsert($apps, ['id'], [
                'app_key','name','url','description','currency_code','date_update_time',
                'sync_update_time','pity_system','guarantee_count',
                'rarity_defs','marker_defs','task_defs','created_at','updated_at'
            ]);

            // apps.id の最大値で sequence を追従
            DB::statement("
                SELECT setval(
                    pg_get_serial_sequence('apps','id'),
                    GREATEST((SELECT COALESCE(MAX(id), 1) FROM apps), 1)
                )
            ");

            // user_apps を作成：id=1,2 は user_id=2、それ以外は user_id=3
            $now = now();
            $userAppsRows = [];
            foreach ($apps as $row) {
                $appId = (int) $row['id'];
                $userId = in_array($appId, [1, 2], true) ? 2 : 3;

                // 既存 created_at/updated_at を揃える。無い場合は now。
                $createdAt = $row['created_at'] ?? $now;
                $updatedAt = $row['updated_at'] ?? $now;

                $userAppsRows[] = [
                    'user_id'    => $userId,
                    'app_id'     => $appId,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ];
            }

            // 同じ (user_id, app_id) が存在しても更新されるよう upsert
            DB::table('user_apps')->upsert(
                $userAppsRows,
                ['user_id', 'app_id'],
                ['updated_at']
            );
        });
    }

    /**
     * CSV を読み込み、apps テーブル用の配列へ変換
     * - currency_unit -> currency_code（3桁大文字）
     * - t/f -> bool
     * - JSON文字列 -> 配列(null/空は null)
     * - created_at/updated_at はそのままタイムゾーン込みで CarbonImmutable 化
     * @return array<int, array<string, mixed>>
     */
    private function readCsv(): array
    {
        $file = new SplFileObject($this->csvPath, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl(',');

        $header = null;
        $rows = [];

        foreach ($file as $line) {
            if ($line === [null] || $line === false) {
                continue;
            }
            if ($header === null) {
                $header = $this->normalizeHeader($line);
                continue;
            }
            $row = $this->combineRow($header, $line);
            if ($row === null) {
                continue;
            }

            $rows[] = $this->mapAppRow($row);
        }

        return $rows;
    }

    private function normalizeHeader(array $raw): array
    {
        return array_map(
            fn ($h) => Str::of((string)$h)->trim()->lower()->toString(),
            $raw
        );
    }

    private function combineRow(array $header, array $line): ?array
    {
        if (count($line) === 1 && ($line[0] === null || $line[0] === '')) {
            return null;
        }
        // ヘッダー数と列数がズレた場合も緩やかに合わせる
        $line = array_pad($line, count($header), null);
        return array_combine($header, $line) ?: null;
    }

    private function tf(mixed $v): bool
    {
        if ($v === null) return false;
        $s = strtolower(trim((string)$v));
        return in_array($s, ['t','true','1','yes','y'], true);
    }

    private function jsonStringOrNull(?string $s): ?string
    {
        $s = $s === null ? null : trim($s);
        if ($s === null || $s === '') return null;
        try {
            $decoded = json_decode($s, true, 512, JSON_THROW_ON_ERROR);
            // 正規化して戻す（\ と Unicode をエスケープしない）
            return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return null; // 不正JSONは捨てる
        }
    }

    private function toCarbon(?string $s): ?CarbonImmutable
    {
        if ($s === null || trim($s) === '') return null;
        try {
            return CarbonImmutable::parse($s);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function mapAppRow(array $r): array
    {
        $createdAt = $this->toCarbon($r['created_at'] ?? null);
        $updatedAt = $this->toCarbon($r['updated_at'] ?? null);

        // 通貨コードは3桁大文字へ
        $currencyUnit = (string)($r['currency_unit'] ?? '');
        $currencyCode = (preg_match('/^[A-Za-z]{3}$/', $currencyUnit))
            ? strtoupper($currencyUnit)
            : 'USD'; // フォールバック

        return [
            'id'               => (int) ($r['id'] ?? 0),
            'app_key'          => (string) ($r['app_key'] ?? Str::ulid()->toString()),
            'name'             => (string) ($r['name'] ?? ''),
            'url'              => $r['url'] !== '' ? (string) $r['url'] : null,
            'description'      => $r['description'] !== '' ? (string) $r['description'] : null,
            'currency_code'    => $currencyCode,
            'date_update_time' => (string) ($r['date_update_time'] ?? '00:00'),
            'sync_update_time' => $this->tf($r['sync_update_time'] ?? null),
            'pity_system'      => $this->tf($r['pity_system'] ?? null),
            'guarantee_count'  => (int) ($r['guarantee_count'] ?? 0),
            'rarity_defs'      => $this->jsonStringOrNull($r['rarity_defs'] ?? null),
            'marker_defs'      => $this->jsonStringOrNull($r['marker_defs'] ?? null),
            'task_defs'        => $this->jsonStringOrNull($r['task_defs'] ?? null),
            'created_at'       => $createdAt,
            'updated_at'       => $updatedAt ?? $createdAt,
        ];
    }
}
