<?php

namespace App\Console\Commands;

use App\Models\{User, App as AppModel, Log, UserSession};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log as LaravelLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ReportDailySummaryCommand extends Command
{
    protected $signature = 'report:daily-summary {--date=} {--dry-run}';
    protected $description = 'ユーザー/アプリの主要指標を日次で集計し、CSV/Markdown を保存します。';

    public function handle(): int
    {
        $tz = 'Asia/Tokyo';
        $dateArg = $this->option('date');
        $date = $dateArg ? \Carbon\Carbon::parse($dateArg, $tz) : now($tz);
        $dry = (bool) $this->option('dry-run');
        $topN = (int) env('REPORT_TOP_N', 10);

        $dir = storage_path('app/reports/' . $date->format('Ymd'));
        if (!is_dir($dir) && !$dry) {
            mkdir($dir, 0775, true);
        }

        $todayStart = $date->copy()->startOfDay()->setTimezone('UTC');
        $yesterdayEnd = $date->copy()->startOfDay()->setTimezone('UTC');
        $activeSince = $date->copy()->subDays(30)->setTimezone('UTC');

        try {
            // 指標
            $usersTotal = User::query()->where('is_deleted', false)->count();
            $usersNewToday = User::query()->where('created_at', '>=', $todayStart)->count();
            $usersActive30d = UserSession::query()->where('created_at', '>=', $activeSince)->distinct('user_id')->count('user_id');

            $appsTotal = AppModel::query()->count();
            $appsNewToday = AppModel::query()->where('created_at', '>=', $todayStart)->count();

            // ロケールは User.language を使用
            $byLanguage = User::query()
                ->selectRaw("COALESCE(language, 'unknown') as language, COUNT(*) as cnt")
                ->where('is_deleted', false)
                ->groupBy('language')
                ->orderByDesc('cnt')->get()->toArray();

            // プラン別（users.plan_id）
            $byPlan = User::query()->selectRaw('plan_id, COUNT(*) as cnt')
                ->where('is_deleted', false)
                ->groupBy('plan_id')->orderByDesc('cnt')->get()->toArray();

            // トップNアプリ（直近30日 logs 件数）
            $topApps = Log::query()
                ->selectRaw('app_id, COUNT(*) as cnt')
                ->where('created_at', '>=', $activeSince)
                ->groupBy('app_id')
                ->orderByDesc('cnt')
                ->limit($topN)
                ->get()
                ->map(function ($row) {
                    $app = AppModel::find($row->app_id);
                    return [
                        'app_id' => $row->app_id,
                        'name'   => $app?->name ?? '-',
                        'count'  => (int) $row->cnt,
                    ];
                })->toArray();
        } catch (\Throwable $e) {
            // テスト環境などテーブル未作成時は空で継続
            $usersTotal = $usersNewToday = $usersActive30d = 0;
            $appsTotal = $appsNewToday = 0;
            $byLanguage = $byPlan = $topApps = [];
        }

        $csv = $this->buildCsv([
            ['users_total', $usersTotal],
            ['users_new_today', $usersNewToday],
            ['users_active_30d', $usersActive30d],
            ['apps_total', $appsTotal],
            ['apps_new_today', $appsNewToday],
        ], $byLanguage, $byPlan, $topApps);

        $md = $this->buildMarkdown($date->toDateString(), $usersTotal, $usersNewToday, $usersActive30d, $appsTotal, $appsNewToday, $byLanguage, $byPlan, $topApps);

        $files = [];
        if ($dry) {
            $this->line($md);
        } else {
            $csvPath = $dir . '/summary_' . $date->format('Ymd') . '.csv';
            $mdPath  = $dir . '/summary_' . $date->format('Ymd') . '.md';
            file_put_contents($csvPath, $csv);
            file_put_contents($mdPath, $md);
            $files = [$csvPath, $mdPath];
        }

        // 保持ポリシー
        $this->purgeOld((int) env('RETENTION_DAYS', 14));

        $payload = compact('usersTotal','usersNewToday','usersActive30d','appsTotal','appsNewToday') + ['dir' => $dir];
        $this->notify('success', $payload);
        $this->info('Report generated: ' . json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }

    private function buildCsv(array $kv, array $byLang, array $byPlan, array $topApps): string
    {
        $lines = [];
        foreach ($kv as [$k,$v]) { $lines[] = "$k,$v"; }
        $lines[] = '';
        $lines[] = 'language,count';
        foreach ($byLang as $row) { $lines[] = ($row['language'] ?? 'unknown') . ',' . $row['cnt']; }
        $lines[] = '';
        $lines[] = 'plan_id,count';
        foreach ($byPlan as $row) { $lines[] = ($row['plan_id'] ?? 'null') . ',' . $row['cnt']; }
        $lines[] = '';
        $lines[] = 'top_apps_app_id,name,count';
        foreach ($topApps as $row) { $lines[] = $row['app_id'] . ',' . str_replace([",","\n"],' ', $row['name']) . ',' . $row['count']; }
        return implode("\n", $lines) . "\n";
    }

    private function buildMarkdown(string $date, int $uTotal, int $uNew, int $uActive, int $aTotal, int $aNew, array $byLang, array $byPlan, array $topApps): string
    {
        $md = [];
        $md[] = '# Daily Summary (' . $date . ')';
        $md[] = '';
        $md[] = '- Users: total ' . $uTotal . ', new ' . $uNew . ', active30d ' . $uActive;
        $md[] = '- Apps: total ' . $aTotal . ', new ' . $aNew;
        $md[] = '';
        $md[] = '## By Language';
        foreach ($byLang as $r) { $md[] = sprintf('- %s: %d', $r['language'] ?? 'unknown', $r['cnt']); }
        $md[] = '';
        $md[] = '## By Plan';
        foreach ($byPlan as $r) { $md[] = sprintf('- plan:%s => %d', $r['plan_id'] ?? 'null', $r['cnt']); }
        $md[] = '';
        $md[] = '## Top Apps (30d)';
        foreach ($topApps as $r) { $md[] = sprintf('- [%d] %s: %d', $r['app_id'], $r['name'], $r['count']); }
        $md[] = '';
        return implode("\n", $md);
    }

    private function purgeOld(int $days): void
    {
        if ($days <= 0) return;
        $root = storage_path('app/reports');
        if (!is_dir($root)) return;
        $limit = now('Asia/Tokyo')->subDays($days)->format('Ymd');
        foreach (scandir($root) as $d) {
            if (!preg_match('/^\\d{8}$/', $d)) continue;
            if ($d < $limit) { @\Illuminate\Support\Facades\File::deleteDirectory($root . '/' . $d); }
        }
    }

    private function notify(string $status, array $payload): void
    {
        $to = (string) env('BATCH_NOTIFY_EMAIL', 'admin@pulllog.net');
        $subj = sprintf('[Pulllog] report:daily-summary %s - %s', $status, now('Asia/Tokyo')->toDateString());
        $body = $subj . "\n" . json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        try { Mail::raw($body, function ($m) use ($to, $subj) { $m->to($to)->subject($subj); }); }
        catch (\Throwable $e) { LaravelLog::warning('daily-summary notify failed', ['error' => $e->getMessage()]); }
    }
}
