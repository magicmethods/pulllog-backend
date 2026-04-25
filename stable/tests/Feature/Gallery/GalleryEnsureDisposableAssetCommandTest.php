<?php

namespace Tests\Feature\Gallery;

use App\Models\GalleryAsset;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryEnsureDisposableAssetCommandTest extends TestCase
{
    use RefreshDatabase;

    private const MARKER = 'FE-G5-DISPOSABLE';

    public function test_it_normalizes_an_existing_active_marked_asset(): void
    {
        $user = $this->createUserForPlan($this->createPlan()->id);

        $asset = GalleryAsset::factory()->create([
            'user_id' => $user->id,
            'title' => self::MARKER,
            'tags' => ['daily'],
        ]);

        $this->artisan('gallery:ensure-disposable-asset', [
            '--user-email' => $user->email,
            '--marker' => self::MARKER,
        ])
            ->expectsOutputToContain("asset:active {$asset->id} marker:" . self::MARKER)
            ->assertExitCode(0);

        $asset->refresh();

        $this->assertSame(self::MARKER, $asset->title);
        $this->assertContains(self::MARKER, $asset->tags ?? []);
        $this->assertSame(1, GalleryAsset::query()->where('user_id', $user->id)->count());
    }

    public function test_it_restores_a_soft_deleted_marked_asset(): void
    {
        $user = $this->createUserForPlan($this->createPlan()->id);

        $asset = GalleryAsset::factory()->create([
            'user_id' => $user->id,
            'title' => self::MARKER,
            'tags' => ['daily'],
        ]);
        $asset->delete();

        $this->assertSoftDeleted($asset);

        $this->artisan('gallery:ensure-disposable-asset', [
            '--user-email' => $user->email,
            '--marker' => self::MARKER,
        ])
            ->expectsOutputToContain("asset:restored {$asset->id} marker:" . self::MARKER)
            ->assertExitCode(0);

        $restoredAsset = GalleryAsset::withTrashed()->findOrFail($asset->id);

        $this->assertNull($restoredAsset->deleted_at);
        $this->assertContains(self::MARKER, $restoredAsset->tags ?? []);
        $this->assertSame(1, GalleryAsset::query()->where('user_id', $user->id)->count());
    }

    public function test_it_creates_a_new_disposable_asset_when_the_user_has_no_assets(): void
    {
        $user = $this->createUserForPlan($this->createPlan()->id);

        $this->artisan('gallery:ensure-disposable-asset', [
            '--user-email' => $user->email,
            '--marker' => self::MARKER,
        ])
            ->expectsOutputToContain('asset:created')
            ->assertExitCode(0);

        $createdAsset = GalleryAsset::query()
            ->where('user_id', $user->id)
            ->sole();

        $this->assertSame(self::MARKER, $createdAsset->title);
        $this->assertSame('E2E disposable gallery asset', $createdAsset->description);
        $this->assertContains(self::MARKER, $createdAsset->tags ?? []);
    }

    public function test_it_does_not_repurpose_an_existing_unmarked_asset(): void
    {
        $user = $this->createUserForPlan($this->createPlan()->id);

        $originalAsset = GalleryAsset::factory()->create([
            'user_id' => $user->id,
            'title' => 'Original title',
            'tags' => ['daily'],
        ]);

        $this->artisan('gallery:ensure-disposable-asset', [
            '--user-email' => $user->email,
            '--marker' => self::MARKER,
        ])
            ->expectsOutputToContain('asset:created')
            ->assertExitCode(0);

        $originalAsset->refresh();

        $this->assertSame('Original title', $originalAsset->title);
        $this->assertSame(['daily'], $originalAsset->tags);

        $markerAsset = GalleryAsset::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $originalAsset->id)
            ->sole();

        $this->assertSame(self::MARKER, $markerAsset->title);
        $this->assertContains(self::MARKER, $markerAsset->tags ?? []);
        $this->assertSame(2, GalleryAsset::query()->where('user_id', $user->id)->count());
    }

    private function createPlan(int $maxGalleryMb = 300, int $maxUploadMb = 20): Plan
    {
        return Plan::create([
            'name' => 'Test Plan ' . uniqid(),
            'description' => null,
            'max_apps' => 5,
            'max_app_name_length' => 30,
            'max_app_desc_length' => 400,
            'max_log_tags' => 5,
            'max_log_tag_length' => 22,
            'max_log_text_length' => 250,
            'max_logs_per_app' => -1,
            'max_gallery_mb' => $maxGalleryMb,
            'max_upload_mb_per_file' => $maxUploadMb,
            'external_storage_allowed' => false,
            'transcode_webp' => true,
            'max_storage_mb' => 500,
            'price_per_month' => 0,
            'is_active' => true,
        ]);
    }

    private function createUserForPlan(int $planId): User
    {
        return User::factory()->create([
            'plan_id' => $planId,
            'roles' => ['user'],
            'plan_expiration' => now()->addYear(),
            'language' => 'en',
            'theme' => 'light',
            'home_page' => '/apps',
            'is_deleted' => false,
            'is_verified' => true,
        ]);
    }
}