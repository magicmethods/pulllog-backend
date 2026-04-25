<?php

namespace App\Http\Controllers\Gallery;

use App\Http\Controllers\Controller;
use App\Services\PlanLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GalleryUsageController extends Controller
{
    public function show(Request $request, PlanLimitService $planLimitService)
    {
        $startedAt = hrtime(true);
        $user = $request->user();
        $limits = $planLimitService->getGalleryLimitsForUser($user->id);
        $usage = DB::table('gallery_usage_stats')->where('user_id', $user->id)->first();

        $used = (int) ($usage->bytes_used ?? 0);
        $max = (int) $limits['max_gallery_bytes'];

        $elapsedMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);
        $slowLogThresholdMs = (int) config('gallery.usage_slow_log_ms', 1000);
        if ($elapsedMs >= $slowLogThresholdMs) {
            Log::warning('gallery.usage.show.slow', [
                'user_id' => (int) $user->id,
                'elapsed_ms' => $elapsedMs,
                'used_bytes' => $used,
                'max_bytes' => $max,
                'file_count' => (int) ($usage->files_count ?? 0),
            ]);
        }

        return response()->json([
            'usedBytes' => $used,
            'maxBytes' => $max,
            'remainingBytes' => max($max - $used, 0),
            'filesCount' => (int) ($usage->files_count ?? 0),
        ]);
    }
}
