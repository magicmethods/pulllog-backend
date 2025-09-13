<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class DailyBatchCommandsTest extends TestCase
{
    public function test_report_daily_summary_dry_run_outputs_markdown(): void
    {
        $this->artisan('report:daily-summary', ['--dry-run' => true])
            ->expectsOutputToContain('# Daily Summary (')
            ->assertExitCode(0);
    }

    public function test_db_backup_dry_run_outputs_commands(): void
    {
        $this->artisan('db:backup', ['--dry-run' => true])
            ->expectsOutputToContain('pg_dump')
            ->expectsOutputToContain('gzip')
            ->assertExitCode(0);
    }
}

