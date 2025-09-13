<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class DbBackupCommand extends Command
{
    protected $signature = 'db:backup {--date=} {--dry-run : 実行内容のみ表示して終了}';
    protected $description = 'PostgreSQL をフルダンプし、gzip圧縮＋（任意で）AES-256暗号化して保存します。';

    public function handle(): int
    {
        $tz = 'Asia/Tokyo';
        $dateArg = $this->option('date');
        $date = $dateArg ? \Carbon\Carbon::parse($dateArg, $tz) : now($tz);
        $baseDir = storage_path('app/backups/' . $date->format('Ymd'));
        $dry = (bool) $this->option('dry-run');

        $this->info("[db:backup] date={$date->toDateString()} dry={$dry}");

        // DB 接続情報
        $host = (string) env('DB_HOST', 'localhost');
        $port = (string) env('DB_PORT', '5432');
        $db   = (string) env('DB_DATABASE', 'pulllog');
        $user = (string) env('DB_USERNAME', '');
        $pass = (string) env('DB_PASSWORD', '');
        $pgDump = (string) env('PG_DUMP_PATH', 'pg_dump');
        $key = (string) env('BACKUP_ENCRYPTION_KEY', '');

        if (!is_dir($baseDir) && !$dry) {
            mkdir($baseDir, 0775, true);
        }

        $stamp = now('UTC')->format('Ymd_His');
        $dumpPath = $baseDir . "/{$db}_{$stamp}.dump";
        $gzPath   = $dumpPath . '.gz';
        $encPath  = $gzPath . '.enc';

        $steps = [];
        $steps[] = [
            'cmd' => [$pgDump, '-Fc', '-h', $host, '-p', $port, '-U', $user, $db, '-f', $dumpPath],
            'env' => ['PGPASSWORD' => $pass],
            'desc'=> 'pg_dump',
        ];
        $steps[] = [
            'cmd' => ['gzip', '-f', $dumpPath],
            'env' => [],
            'desc'=> 'gzip',
        ];
        if ($key !== '') {
            $steps[] = [
                'cmd' => ['openssl', 'enc', '-aes-256-cbc', '-pbkdf2', '-salt', '-pass', 'pass:' . $key, '-in', $gzPath, '-out', $encPath],
                'env' => [],
                'desc'=> 'openssl-encrypt',
            ];
        }

        $summary = [
            'baseDir' => $baseDir,
            'files'   => [],
            'duration'=> null,
        ];
        $start = microtime(true);

        if ($dry) {
            foreach ($steps as $s) {
                $this->line('$ ' . implode(' ', array_map('escapeshellarg', $s['cmd'])));
            }
            return self::SUCCESS;
        }

        try {
            foreach ($steps as $s) {
                $proc = new Process($s['cmd'], null, array_merge($_ENV, $_SERVER, $s['env']));
                $proc->setTimeout(3600);
                $proc->run(function ($type, $buffer) {
                    $this->output->write($buffer);
                });
                if (!$proc->isSuccessful()) {
                    throw new \RuntimeException($s['desc'] . ' failed: ' . $proc->getErrorOutput());
                }
            }

            // 暗号化したら .gz は削除
            if ($key !== '' && file_exists($gzPath)) {
                @unlink($gzPath);
            }

            $summary['duration'] = round((microtime(true) - $start), 2) . 's';
            $summary['files'] = array_values(array_filter([$encPath, $gzPath]));

            // 保持ポリシー
            $this->purgeOld((int) env('RETENTION_DAYS', 14));

            $this->info('Backup completed: ' . json_encode($summary, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
            $this->notify('success', $summary);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('db:backup failed', ['error' => $e->getMessage()]);
            $this->error($e->getMessage());
            $this->notify('failure', ['error' => Str::limit($e->getMessage(), 1000)]);
            return self::FAILURE;
        }
    }

    private function purgeOld(int $days): void
    {
        if ($days <= 0) return;
        $root = storage_path('app/backups');
        if (!is_dir($root)) return;
        $limit = now('Asia/Tokyo')->subDays($days)->format('Ymd');
        foreach (scandir($root) as $d) {
            if (!preg_match('/^\\d{8}$/', $d)) continue;
            if ($d < $limit) {
                @\Illuminate\Support\Facades\File::deleteDirectory($root . '/' . $d);
            }
        }
    }

    private function notify(string $status, array $payload): void
    {
        $to = (string) env('BATCH_NOTIFY_EMAIL', 'admin@pulllog.net');
        $subj = sprintf('[Pulllog] db:backup %s - %s', $status, now('Asia/Tokyo')->toDateString());
        $body = $subj . "\n" . json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        try {
            Mail::raw($body, function ($m) use ($to, $subj) { $m->to($to)->subject($subj); });
        } catch (\Throwable $e) {
            Log::warning('db:backup notify failed', ['error' => $e->getMessage()]);
        }
    }
}

