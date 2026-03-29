<?php

namespace App\Http\Controllers\Gallery;

use App\Http\Controllers\Controller;
use App\Services\PlanLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GalleryUsageController extends Controller
{
    public function show(Request $request, PlanLimitService $planLimitService)
    {
        $user = $request->user();
        $limits = $planLimitService->getGalleryLimitsForUser($user->id);
        $usage = DB::table('gallery_usage_stats')->where('user_id', $user->id)->first();

        $used = (int) ($usage->bytes_used ?? 0);
        $max = (int) $limits['max_gallery_bytes'];

        return response()->json([
            'usedBytes' => $used,
            'maxBytes' => $max,
            'remainingBytes' => max($max - $used, 0),
            'filesCount' => (int) ($usage->files_count ?? 0),
        ]);
    }
}
