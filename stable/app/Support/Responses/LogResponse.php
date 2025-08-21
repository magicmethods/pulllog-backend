<?php

namespace App\Support\Responses;

use App\Models\App;
use App\Models\Log;
use Carbon\Carbon;

final class LogResponse
{
    /**
     * Log 1件を API レスポンス形へ整形（expense_decimal を含む）
     */
    public static function toArray(Log $log, App $app): array
    {
        // 通貨の小数桁（minor_unit）を解決
        $app->loadMissing('currency');
        $minorUnit = (int) ($app->currency?->minor_unit ?? 0);

        $amount  = (int) ($log->expense_amount ?? 0);
        $decimal = $log->expense_amount === null
            ? null
            : $amount / (10 ** max($minorUnit, 0));

        return [
            'id'               => $log->id,
            'appId'            => $app->app_key,
            'date'             => Carbon::parse($log->log_date)->format('Y-m-d'),
            'total_pulls'      => (int) $log->total_pulls,
            'discharge_items'  => (int) $log->discharge_items,
            // 互換のため expense も返す（内部は expense_amount）
            'expense'          => $amount,
            'expense_decimal'  => $decimal,
            'drop_details'     => $log->drop_details ?? [],
            'tags'             => $log->tags ?? [],
            'free_text'        => $log->free_text,
            'images'           => $log->images ?? [],
            'tasks'            => $log->tasks ?? [],
            'last_updated'     => optional($log->updated_at)->toIso8601String(),
        ];
    }
}
