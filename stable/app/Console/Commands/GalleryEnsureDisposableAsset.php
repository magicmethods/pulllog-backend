<?php

namespace App\Console\Commands;

use App\Models\GalleryAsset;
use App\Models\User;
use Illuminate\Console\Command;

class GalleryEnsureDisposableAsset extends Command
{
    protected $signature = 'gallery:ensure-disposable-asset {--user-email=} {--asset-id=} {--marker=}';

    protected $description = 'Ensure a marker-tagged disposable gallery asset exists by restoring or normalizing a soft-deleted asset when needed.';

    public function handle(): int
    {
        $marker = $this->resolveMarker();
        $assetId = trim((string) $this->option('asset-id'));
        if ($assetId !== '') {
            return $this->restoreAssetById($assetId, $marker);
        }

        $userEmail = trim((string) $this->option('user-email'));
        if ($userEmail === '') {
            $this->error('Pass either --asset-id or --user-email.');
            return self::FAILURE;
        }

        $user = User::query()->where('email', $userEmail)->first();
        if (!$user) {
            $this->error("User not found for email: {$userEmail}");
            return self::FAILURE;
        }

        $activeMarkedAsset = GalleryAsset::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($marker): void {
                $query
                    ->where('title', 'like', "%{$marker}%")
                    ->orWhereJsonContains('tags', $marker);
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->first();

        if ($activeMarkedAsset) {
            return $this->activateDisposableAsset($activeMarkedAsset, $marker);
        }

        $deletedMarkedAsset = GalleryAsset::onlyTrashed()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($marker): void {
                $query
                    ->where('title', 'like', "%{$marker}%")
                    ->orWhereJsonContains('tags', $marker);
            })
            ->orderByDesc('deleted_at')
            ->orderByDesc('updated_at')
            ->first();

        if ($deletedMarkedAsset) {
            return $this->restoreDisposableAsset($deletedMarkedAsset, $marker);
        }

        return $this->createDisposableAsset($user->id, $marker);
    }

    private function restoreAssetById(string $assetId, string $marker): int
    {
        $asset = GalleryAsset::withTrashed()->where('id', $assetId)->first();

        if (!$asset) {
            $this->error("Gallery asset not found: {$assetId}");
            return self::FAILURE;
        }

        if ($asset->trashed()) {
            return $this->restoreDisposableAsset($asset, $marker);
        }

        return $this->activateDisposableAsset($asset, $marker);
    }

    private function activateDisposableAsset(GalleryAsset $asset, string $marker): int
    {
        $this->normalizeDisposableAsset($asset, $marker);

        $this->info("asset:active {$asset->id} marker:{$marker}");

        return self::SUCCESS;
    }

    private function restoreDisposableAsset(GalleryAsset $asset, string $marker): int
    {
        if ($asset->trashed()) {
            $asset->restore();
        }

        $this->normalizeDisposableAsset($asset, $marker);

        $this->info("asset:restored {$asset->id} marker:{$marker}");

        return self::SUCCESS;
    }

    private function createDisposableAsset(int $userId, string $marker): int
    {
        $asset = GalleryAsset::factory()->create([
            'user_id' => $userId,
            'title' => $marker,
            'description' => 'E2E disposable gallery asset',
            'tags' => [$marker, 'e2e', 'disposable'],
            'visibility' => 'private',
        ]);

        $this->info("asset:created {$asset->id} marker:{$marker}");

        return self::SUCCESS;
    }

    private function normalizeDisposableAsset(GalleryAsset $asset, string $marker): void
    {
        $title = trim((string) $asset->title);
        $normalizedTitle = str_contains($title, $marker)
            ? $title
            : trim("{$marker} {$title}");
        $tags = is_array($asset->tags) ? $asset->tags : [];

        if (!in_array($marker, $tags, true)) {
            $tags[] = $marker;
        }

        if ($asset->title !== $normalizedTitle || $asset->tags !== $tags) {
            $asset->forceFill([
                'title' => $normalizedTitle,
                'tags' => array_values($tags),
            ])->save();
        }
    }

    private function resolveMarker(): string
    {
        $marker = trim((string) $this->option('marker'));

        return $marker !== '' ? $marker : 'FE-G5-DISPOSABLE';
    }
}