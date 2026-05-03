<?php

namespace App\Http\Controllers\Gallery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gallery\StoreGalleryAssetRequest;
use App\Http\Requests\Gallery\StoreUploadTicketRequest;
use App\Http\Requests\Gallery\UpdateGalleryAssetRequest;
use App\Http\Resources\GalleryAssetResource;
use App\Models\App;
use App\Models\GalleryAsset;
use App\Models\Log as LogModel;
use App\Services\Gallery\GalleryAssetLinkService;
use App\Services\Gallery\GalleryStorage;
use App\Services\Gallery\GalleryUploadTicketService;
use App\Services\PlanLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GalleryAssetController extends Controller
{
    public function index(Request $request)
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

        $per = (int) $request->input('per', 30);
        $per = max(1, min($per, 100));

        $paginator = $query->paginate($per)->withQueryString();

        $elapsedMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);
        $slowLogThresholdMs = (int) config('gallery.list_slow_log_ms', 1500);

        if ($elapsedMs >= $slowLogThresholdMs) {
            Log::warning('gallery.assets.index.slow', [
                'user_id' => (int) $user->id,
                'elapsed_ms' => $elapsedMs,
                'per' => $per,
                'page' => (int) $request->input('page', 1),
                'result_count' => $paginator->count(),
                'total' => $paginator->total(),
                'has_filters' => [
                    'from' => $request->filled('from'),
                    'to' => $request->filled('to'),
                    'log_id' => $request->filled('log_id'),
                    'tags' => $request->filled('tags'),
                    'q' => $request->filled('q'),
                ],
            ]);
        }

        return GalleryAssetResource::collection($paginator);
    }

    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $asset = GalleryAsset::with(['app:id,app_key,name', 'link:asset_id,code'])->where('id', $id)->firstOrFail();

        if (!$user || (int) $asset->user_id !== (int) $user->id) {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        return new GalleryAssetResource($asset->loadMissing('link:asset_id,code'));
    }

    public function store(
        StoreGalleryAssetRequest $request,
        GalleryStorage $storage,
        PlanLimitService $planLimitService,
        GalleryUploadTicketService $ticketService,
        GalleryAssetLinkService $linkService
    ) {
        $user = $request->user();
        $file = $request->file('file');

        $tokenValue = $request->header('x-upload-token');
        if (!$tokenValue) {
            return response()->json([
                'message' => 'Upload token required',
            ], 401);
        }

        $ticket = $ticketService->findForUserOrFail($user->id, $tokenValue);

        $clientMime = $file->getMimeType();
        if ($ticket->mime !== null && $ticket->mime !== $clientMime) {
            return response()->json([
                'message' => 'MIME type does not match upload ticket',
                'expected' => $ticket->mime,
                'actual' => $clientMime,
            ], 422);
        }

        $fileBytes = $file->getSize() ?: 0;
        if ($fileBytes > (int) $ticket->max_bytes) {
            return response()->json([
                'message' => 'Upload exceeds allowed size from ticket',
                'maxBytes' => (int) $ticket->max_bytes,
                'actualBytes' => $fileBytes,
            ], 403);
        }

        $limits = $planLimitService->getGalleryLimitsForUser($user->id);
        $usageRow = DB::table('gallery_usage_stats')->where('user_id', $user->id)->first();
        $usedBytes = (int) ($usageRow->bytes_used ?? 0);
        $maxBytes = (int) $limits['max_gallery_bytes'];
        $maxPerFile = (int) $limits['max_upload_bytes_per_file'];

        $originalBytes = $file->getSize() ?: 0;
        $estimate = $originalBytes
            + (int) round($originalBytes * config('gallery.estimate_ratio.small', 0.08))
            + (int) round($originalBytes * config('gallery.estimate_ratio.large', 0.20));

        if ($originalBytes > $maxPerFile) {
            return response()->json([
                'message' => 'File exceeds per-file quota',
                'maxBytesPerFile' => $maxPerFile,
                'actualBytes' => $originalBytes,
            ], 403);
        }

        if ($usedBytes + $estimate > $maxBytes) {
            return response()->json([
                'message' => 'Storage quota exceeded',
                'usedBytes' => $usedBytes,
                'maxBytes' => $maxBytes,
            ], 403);
        }

        $hash = hash_file('sha256', $file->getRealPath());
        $duplicate = GalleryAsset::withTrashed()
            ->where('user_id', $user->id)
            ->where('hash_sha256', $hash)
            ->first();

        if ($duplicate && !$duplicate->trashed()) {
            return response()->json([
                'message' => 'Duplicate file',
                'asset' => new GalleryAssetResource(
                    $duplicate->load(['app:id,app_key,name', 'link:asset_id,code'])
                ),
            ], 409);
        }

        $resolvedLogId = $request->filled('log_id')
            ? (int) $request->input('log_id')
            : ($ticket->log_id !== null ? (int) $ticket->log_id : null);
        $resolvedVisibility = $request->input('visibility', $ticket->visibility ?? 'private');
        $resolvedTags = $request->input('tags', $ticket->tags ?? []);
        $resolvedAppId = $ticket->app_id !== null ? (int) $ticket->app_id : null;

        if ($request->filled('app_key')) {
            $app = App::where('app_key', $request->input('app_key'))->first();
            if (!$app) {
                return response()->json([
                    'message' => 'App not found',
                ], 422);
            }

            $ownsApp = DB::table('user_apps')
                ->where('user_id', $user->id)
                ->where('app_id', $app->id)
                ->exists();

            if (!$ownsApp) {
                return response()->json([
                    'message' => 'App is not available for user',
                ], 422);
            }

            $resolvedAppId = (int) $app->id;
        }

        $log = null;
        if ($resolvedLogId !== null) {
            $log = LogModel::where('id', (int) $resolvedLogId)
                ->where('user_id', $user->id)
                ->first();
            if (!$log) {
                return response()->json([
                    'message' => 'Log not found for user',
                ], 422);
            }

            if ($log->app_id !== null) {
                $logAppId = (int) $log->app_id;

                if ($resolvedAppId !== null && $resolvedAppId !== $logAppId) {
                    return response()->json([
                        'message' => 'App does not match the log',
                    ], 422);
                }

                $resolvedAppId = $logAppId;
            }
        }

        if ($duplicate && $duplicate->trashed()) {
            $asset = DB::transaction(function () use (
                $request,
                $duplicate,
                $resolvedLogId,
                $resolvedAppId,
                $resolvedVisibility,
                $resolvedTags,
                $linkService,
                $ticketService,
                $ticket
            ) {
                $duplicate->restore();
                $duplicate->forceFill([
                    'app_id' => $resolvedAppId,
                    'log_id' => $resolvedLogId,
                    'title' => $request->input('title'),
                    'description' => $request->input('description'),
                    'tags' => $resolvedTags,
                    'visibility' => $resolvedVisibility,
                ])->save();

                $ticketService->markAsUsed($ticket);

                if ($duplicate->visibility === 'public') {
                    $link = $linkService->createOrRefreshLink($duplicate);
                    $duplicate->setRelation('link', $link);
                } else {
                    $linkService->deleteLink($duplicate);
                    $duplicate->setRelation('link', null);
                }

                return $duplicate->loadMissing('app:id,app_key,name', 'link:asset_id,code');
            });

            return (new GalleryAssetResource($asset))
                ->response()
                ->setStatusCode(201);
        }

        $disk = config('gallery.disk');
        $baseDir = config('gallery.base_dir');

        $saved = null;
        $asset = null;

        try {
            $saved = $storage->saveWithThumbnails($file, $disk, $baseDir);

            $finalTotal = $saved['bytes']
                + (int) ($saved['small']['bytes'] ?? 0)
                + (int) ($saved['large']['bytes'] ?? 0);

            if ($usedBytes + $finalTotal > $maxBytes) {
                $this->deleteStored($disk, $saved);
                $saved = null;

                return response()->json([
                    'message' => 'Storage quota exceeded (final check)',
                    'usedBytes' => $usedBytes,
                    'maxBytes' => $maxBytes,
                ], 403);
            }

            $asset = DB::transaction(function () use (
                $request,
                $user,
                $hash,
                $disk,
                $saved,
                $resolvedLogId,
                $resolvedAppId,
                $resolvedVisibility,
                $resolvedTags,
                $ticketService,
                $ticket,
                $clientMime
            ) {
                $asset = GalleryAsset::create([
                    'user_id' => $user->id,
                    'app_id' => $resolvedAppId,
                    'log_id' => $resolvedLogId,
                    'disk' => $disk,
                    'path' => $saved['path'],
                    'thumb_path_small' => $saved['small']['path'] ?? null,
                    'thumb_path_large' => $saved['large']['path'] ?? null,
                    'mime' => $clientMime ?? 'application/octet-stream',
                    'bytes' => (int) $saved['bytes'],
                    'bytes_thumb_small' => (int) ($saved['small']['bytes'] ?? 0),
                    'bytes_thumb_large' => (int) ($saved['large']['bytes'] ?? 0),
                    'width' => (int) $saved['width'],
                    'height' => (int) $saved['height'],
                    'hash_sha256' => $hash,
                    'title' => $request->input('title'),
                    'description' => $request->input('description'),
                    'tags' => $resolvedTags,
                    'visibility' => $resolvedVisibility,
                ]);

                $ticketService->markAsUsed($ticket);

                return $asset->load('app:id,app_key,name');
            });
        } catch (Throwable $e) {
            if ($saved !== null) {
                $this->deleteStored($disk, $saved);
            }

            throw $e;
        }

        if ($asset->visibility === 'public') {
            $link = $linkService->createOrRefreshLink($asset);
            $asset->setRelation('link', $link);
        } else {
            $linkService->deleteLink($asset);
            $asset->setRelation('link', null);
        }

        $asset->loadMissing('link:asset_id,code');

        return (new GalleryAssetResource($asset))
            ->response()
            ->setStatusCode(201);
    }

    public function uploadTicket(
        StoreUploadTicketRequest $request,
        GalleryUploadTicketService $ticketService
    ) {
        $user = $request->user();
        $payload = $request->validatedPayload();

        if (empty($payload['visibility'])) {
            $payload['visibility'] = 'private';
        }

        $ticket = $ticketService->issue($user, $payload);

        return response()->json($ticket);
    }

    public function content(Request $request, string $id)
    {
        if (!$request->hasValidSignature(false)) {
            abort(403);
        }

        $asset = GalleryAsset::where('id', $id)->firstOrFail();

        if ($asset->visibility === 'public') {
            $disk = $asset->disk ?? config('gallery.disk');
            /** @var FilesystemAdapter $filesystem */
            $filesystem = Storage::disk($disk);
            if (!$filesystem->exists($asset->path)) {
                abort(404);
            }

            return $filesystem->response($asset->path);
        }

        $requestedUser = (int) $request->query('user');
        if ($requestedUser !== (int) $asset->user_id) {
            abort(403);
        }

        $disk = $asset->disk ?? config('gallery.disk');
        $variant = $request->query('variant', 'original');

        $path = match ($variant) {
            'small' => $asset->thumb_path_small ?? $asset->path,
            'large' => $asset->thumb_path_large ?? $asset->path,
            default => $asset->path,
        };

        /** @var FilesystemAdapter $filesystem */
        $filesystem = Storage::disk($disk);

        if (!$path || !$filesystem->exists($path)) {
            abort(404);
        }

        $headers = [
            'Cache-Control' => 'private, max-age=' . (int) config('gallery.signed_url_ttl', 120),
        ];

        return $filesystem->response($path, null, $headers);
    }

    public function update(
        UpdateGalleryAssetRequest $request,
        string $id,
        GalleryAssetLinkService $linkService
    )
    {
        $asset = GalleryAsset::where('id', $id)->firstOrFail();
        $this->authorize('update', $asset);

        if ($request->filled('log_id')) {
            $log = LogModel::where('id', (int) $request->input('log_id'))
                ->where('user_id', $request->user()->id)
                ->first();
            if (!$log) {
                return response()->json([
                    'message' => 'Log not found for user',
                ], 422);
            }
        }

        $asset->fill($request->only(['title', 'description', 'visibility']));
        if ($request->has('tags')) {
            $asset->tags = $request->input('tags');
        }
        if ($request->filled('log_id')) {
            $asset->log_id = (int) $request->input('log_id');
        }
        $asset->save();

        if ($asset->visibility === 'public') {
            $link = $linkService->createOrRefreshLink($asset);
            $asset->setRelation('link', $link);
        } else {
            $linkService->deleteLink($asset);
            $asset->setRelation('link', null);
        }

        $asset->loadMissing('app:id,app_key,name', 'link:asset_id,code');

        return new GalleryAssetResource($asset);
    }

    public function destroy(Request $request, string $id)
    {
        $asset = GalleryAsset::where('id', $id)->firstOrFail();
        $this->authorize('delete', $asset);
        $asset->delete();

        return response()->noContent();
    }

    private function deleteStored(string $disk, array $saved): void
    {
        $paths = [$saved['path']];
        if (!empty($saved['small']['path'])) {
            $paths[] = $saved['small']['path'];
        }
        if (!empty($saved['large']['path'])) {
            $paths[] = $saved['large']['path'];
        }

        foreach ($paths as $path) {
            if ($path && Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }
    }
}




