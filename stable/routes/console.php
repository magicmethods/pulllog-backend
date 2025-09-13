<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduling (03:30 JST, production only)
Schedule::call(function () {
    $attempt = function (string $cmd): bool {
        $max = 3;
        for ($i = 1; $i <= $max; $i++) {
            $code = \Artisan::call($cmd);
            if ($code === 0) { return true; }
            sleep(60);
        }
        return false;
    };

    $attempt('db:backup');
    $attempt('report:daily-summary');
})->dailyAt('03:30')->timezone('Asia/Tokyo')->when(fn() => app()->environment('production'));
