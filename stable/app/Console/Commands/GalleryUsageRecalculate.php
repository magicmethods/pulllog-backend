<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GalleryUsageRecalculate extends Command
{
    protected $signature = 'gallery:recalculate-usage {--user_id=}';

    protected $description = 'Recalculate gallery_usage_stats from gallery_assets (soft deletes excluded).';

    public function handle(): int
    {
        $userId = $this->option('user_id');

        if ($userId !== null) {
            $this->recalculateForUser((int) $userId);
            $this->info("Recalculated gallery usage for user_id={$userId}");
            return self::SUCCESS;
        }

        $this->recalculateAll();
        $this->info('Recalculated gallery usage for all users');

        return self::SUCCESS;
    }

    private function recalculateForUser(int $userId): void
    {
        $row = $this->buildBaseQuery()->where('user_id', $userId)->first();

        DB::table('gallery_usage_stats')->updateOrInsert(
            ['user_id' => $userId],
            [
                'bytes_used' => (int) ($row->bytes_used ?? 0),
                'files_count' => (int) ($row->files_count ?? 0),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function recalculateAll(): void
    {
        $rows = $this->buildBaseQuery()->get();

        foreach ($rows as $row) {
            DB::table('gallery_usage_stats')->updateOrInsert(
                ['user_id' => $row->user_id],
                [
                    'bytes_used' => (int) $row->bytes_used,
                    'files_count' => (int) $row->files_count,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function buildBaseQuery()
    {
        return DB::table('gallery_assets')
            ->selectRaw('user_id, SUM(bytes + COALESCE(bytes_thumb_small,0) + COALESCE(bytes_thumb_large,0)) AS bytes_used, COUNT(*) AS files_count')
            ->whereNull('deleted_at')
            ->groupBy('user_id');
    }
}
