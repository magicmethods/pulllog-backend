<?php

namespace App\Http\Controllers\Gallery;

use App\Http\Controllers\Controller;
use App\Http\Resources\GalleryAssetResource;
use App\Models\GalleryAsset;
use App\Services\PlanLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GalleryBootstrapController extends Controller
{
    public function __construct(
        private readonly PlanLimitService $planLimitService,
    ) {}

    public function show(Request $request)
    {
        $startedAt = hrtime(true);
        $user = $request->user();

        $query = GalleryAsset::query()
            ->with([
                'app:id,app_key,name',
                'link:asset_id,code',
            ])
            ->where('user_id', $user->id);

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from')->startOfDay());
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to')->endOfDay());
        }

        if ($request->filled('log_id')) {
            $query->where('log_id', (int) $request->input('log_id'));
        }

        if ($request->filled('tags')) {
            $tags = (array) $request->input('tags', []);
            $query->where(function ($sub) use ($tags) {
                foreach ($tags as $tag) {
                    $sub->orWhereJsonContains('tags', $tag);
                }
            });
        }

        if ($request->filled('q')) {
            $keyword = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $request->input('q')) . '%';
            $query->where(function ($sub) use ($keyword) {
                $sub->where('title', 'ILIKE', $keyword)
                    ->orWhere('description', 'ILIKE', $keyword);
            });
        }

        $query->orderByDesc('created_at');

        $per = (int) $request->input('per', 10);
        $per = max(1, min($per, 100));

        $paginator = $query->paginate($per)->withQueryString();

        $limits = $this->planLimitService->getGalleryLimitsForUser($user->id);
        $usage = DB::table('gallery_usage_stats')->where('user_id', $user->id)->first();

        $used = (int) ($usage->bytes_used ?? 0);
        $max = (int) $limits['max_gallery_bytes'];

        $elapsedMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);
        $hardTimeoutMs = (int) config('gallery.bootstrap_hard_timeout_ms', 8000);
        $slowLogMs = (int) config('gallery.bootstrap_slow_log_ms', 2000);

        if ($elapsedMs >= $slowLogMs) {
            Log::warning('gallery.bootstrap.show.slow', [
                'user_id' => (int) $user->id,
                'elapsed_ms' => $elapsedMs,
                'per' => $per,
                'page' => (int) $request->input('page', 1),
                'result_count' => $paginator->count(),
                'total' => $paginator->total(),
            ]);
        }

        if ($elapsedMs >= $hardTimeoutMs) {
            return response()->json(['message' => 'Gateway Timeout'], 504);
        }

        $resource = GalleryAssetResource::collection($paginator);
        $resolved = $resource->response($request)->getData(true);

        return response()->json([
            'data' => [
                'assets' => $resolved['data'],
                'usage' => [
                    'usedBytes' => $used,
                    'maxBytes' => $max,
                    'remainingBytes' => max($max - $used, 0),
                    'filesCount' => (int) ($usage->files_count ?? 0),
                ],
            ],
            'links' => $resolved['links'],
            'meta' => $resolved['meta'],
        ]);
    }
}
