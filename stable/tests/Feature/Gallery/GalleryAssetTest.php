<?php

namespace Tests\Feature\Gallery;

use App\Models\App;
use App\Models\GalleryAsset;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserSession;
use App\Models\UserApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class GalleryAssetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'demo.demo_user_ids' => [],
            'demo.demo_email' => null,
            'api.api_key' => 'test-api-key',
            'gallery.disk' => 'private',
            'api.base_uri' => 'v1',
            'gallery.upload_ticket_ttl' => 60,
            'gallery.public_host' => 'http://img.test',
        ]);
    }

    // Verifies successful upload stores the asset and recalculates usage counters.
    public function test_user_can_upload_gallery_asset(): void
    {
        Storage::fake('private');

        $plan = $this->createPlan();
        $user = $this->createUserForPlan($plan->id);
        $app = $this->createAppForUser($user);
        DB::table('gallery_usage_stats')->insert([
            'user_id' => $user->id,
            'bytes_used' => 0,
            'files_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedSessionForUser($user, 'csrf-token');

        $file = UploadedFile::fake()->image('sample.jpg', 800, 600)->size(500);
        $ticket = $this->issueUploadTicket($user, [
            'appKey' => $app->app_key,
            'expectedBytes' => $file->getSize(),
            'mime' => $file->getMimeType(),
            'visibility' => 'private',
        ]);

        $response = $this->withHeaders([
            'x-api-key' => 'test-api-key',
            'x-csrf-token' => 'csrf-token',
            'x-upload-token' => $ticket['token'],
        ])->actingAs($user)
            ->postJson('/api/v1/gallery/assets', [
                'file' => $file,
                'app_key' => $app->app_key,
            ]);

        $response->assertCreated();

        $asset = GalleryAsset::first();
        $this->assertNotNull($asset);
        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk('private');
        $storage->assertExists($asset->path);

        $payload = $response->json();
        if (isset($payload['data'])) {
            $payload = $payload['data'];
        }
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('url', $payload);
        $this->assertSame($app->id, $payload['appId']);
        $this->assertSame($app->app_key, $payload['appKey']);
        $this->assertNull($payload['publicUrl']);
        $this->assertNotNull($payload['url']);
        $this->assertStringContainsString('signature=', $payload['url']);
        $this->assertStringContainsString('variant=original', $payload['url']);
        $this->get($payload['url'])->assertOk();

        $unsignedPath = parse_url($payload['url'], PHP_URL_PATH);
        $this->get($unsignedPath)->assertForbidden();

        $this->assertArrayHasKey('thumbSmallUrl', $payload);
        if ($payload['thumbSmallUrl']) {
            $this->assertStringContainsString('variant=small', $payload['thumbSmallUrl']);
            $this->get($payload['thumbSmallUrl'])->assertOk();
        }

        $showResponse = $this->withHeaders([
            'x-api-key' => 'test-api-key',
            'x-csrf-token' => 'csrf-token',
        ])->actingAs($user)
            ->getJson('/api/v1/gallery/assets/' . $asset->id);

        $showResponse->assertOk()->assertJsonFragment([
            'id' => (string) $asset->id,
            'appId' => $app->id,
            'appKey' => $app->app_key,
            'userId' => $user->id,
        ]);

        $this->artisan('gallery:recalculate-usage', ['--user_id' => $user->id]);

        $usage = DB::table('gallery_usage_stats')->where('user_id', $user->id)->first();
        $this->assertSame(1, (int) $usage->files_count);
        $this->assertGreaterThan(0, (int) $usage->bytes_used);
    }

    // Confirms duplicate uploads return HTTP 409 and do not create a second record.
    public function test_duplicate_upload_returns_conflict(): void
    {
        Storage::fake('private');

        $plan = $this->createPlan();
        $user = $this->createUserForPlan($plan->id);
        $app = $this->createAppForUser($user);
        DB::table('gallery_usage_stats')->insert([
            'user_id' => $user->id,
            'bytes_used' => 0,
            'files_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedSessionForUser($user, 'csrf-token');

        $file = UploadedFile::fake()->image('duplicate.jpg', 800, 600)->size(400);

        $headers = [
            'x-api-key' => 'test-api-key',
            'x-csrf-token' => 'csrf-token',
        ];

        $firstTicket = $this->issueUploadTicket($user, [
            'appKey' => $app->app_key,
            'expectedBytes' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ]);

        $this->withHeaders($headers + ['x-upload-token' => $firstTicket['token']])->actingAs($user)
            ->postJson('/api/v1/gallery/assets', ['file' => $file, 'app_key' => $app->app_key])
            ->assertCreated();

        $secondTicket = $this->issueUploadTicket($user, [
            'appKey' => $app->app_key,
            'expectedBytes' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ]);

        $this->withHeaders($headers + ['x-upload-token' => $secondTicket['token']])->actingAs($user)
            ->postJson('/api/v1/gallery/assets', ['file' => $file, 'app_key' => $app->app_key])
            ->assertStatus(409);

        $this->assertSame(1, GalleryAsset::count());
    }

    // Ensures uploads that exceed the plan quota are rejected with HTTP 403.
    public function test_upload_over_quota_is_rejected(): void
    {
        Storage::fake('private');

        $plan = $this->createPlan(maxGalleryMb: 1, maxUploadMb: 10);
        $user = $this->createUserForPlan($plan->id);
        $app = $this->createAppForUser($user);
        DB::table('gallery_usage_stats')->insert([
            'user_id' => $user->id,
            'bytes_used' => 0,
            'files_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedSessionForUser($user, 'csrf-token');

        $file = UploadedFile::fake()->image('large.jpg', 2000, 2000)->size(900);

        $ticket = $this->issueUploadTicket($user, [
            'appKey' => $app->app_key,
            'expectedBytes' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ]);

        $this->withHeaders([
            'x-api-key' => 'test-api-key',
            'x-csrf-token' => 'csrf-token',
            'x-upload-token' => $ticket['token'],
        ])->actingAs($user)
            ->postJson('/api/v1/gallery/assets', ['file' => $file, 'app_key' => $app->app_key])
            ->assertStatus(403);

        $this->assertSame(0, GalleryAsset::count());
    }

    public function test_public_asset_generates_short_url(): void
    {
        Storage::fake('private');

        $plan = $this->createPlan();
        $user = $this->createUserForPlan($plan->id);
        $app = $this->createAppForUser($user);
        DB::table('gallery_usage_stats')->insert([
            'user_id' => $user->id,
            'bytes_used' => 0,
            'files_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedSessionForUser($user, 'csrf-token');

        $file = UploadedFile::fake()->image('public.jpg', 800, 600)->size(400);
        $ticket = $this->issueUploadTicket($user, [
            'appKey' => $app->app_key,
            'expectedBytes' => $file->getSize(),
            'mime' => $file->getMimeType(),
            'visibility' => 'public',
        ]);

        $response = $this->withHeaders([
            'x-api-key' => 'test-api-key',
            'x-csrf-token' => 'csrf-token',
            'x-upload-token' => $ticket['token'],
        ])->actingAs($user)
            ->postJson('/api/v1/gallery/assets', [
                'file' => $file,
                'app_key' => $app->app_key,
                'visibility' => 'public',
            ]);

        $response->assertCreated();

        $payload = $response->json('data');
        $this->assertNotNull($payload['publicUrl']);
        $this->assertNull($payload['url']);

        $publicUrl = $payload['publicUrl'];
        $this->assertStringStartsWith('http://img.test/', $publicUrl);

        $asset = GalleryAsset::first();
        $this->assertNotNull($asset->link);
        $this->assertSame($asset->link->code, substr($publicUrl, strlen('http://img.test/')));

        $publicPath = parse_url($publicUrl, PHP_URL_PATH);

        $this->get('http://img.test' . $publicPath)
            ->assertOk();
    }

    public function test_public_link_removed_when_visibility_changes(): void
    {
        Storage::fake('private');

        $plan = $this->createPlan();
        $user = $this->createUserForPlan($plan->id);
        $app = $this->createAppForUser($user);
        DB::table('gallery_usage_stats')->insert([
            'user_id' => $user->id,
            'bytes_used' => 0,
            'files_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedSessionForUser($user, 'csrf-token');

        $file = UploadedFile::fake()->image('toggle.jpg', 800, 600)->size(400);
        $ticket = $this->issueUploadTicket($user, [
            'appKey' => $app->app_key,
            'expectedBytes' => $file->getSize(),
            'mime' => $file->getMimeType(),
            'visibility' => 'public',
        ]);

        $store = $this->withHeaders([
            'x-api-key' => 'test-api-key',
            'x-csrf-token' => 'csrf-token',
            'x-upload-token' => $ticket['token'],
        ])->actingAs($user)
            ->postJson('/api/v1/gallery/assets', [
                'file' => $file,
                'app_key' => $app->app_key,
                'visibility' => 'public',
            ]);

        $assetId = $store->json('data.id');
        $publicUrl = $store->json('data.publicUrl');
        $this->assertNotNull($publicUrl);
        $publicPath = parse_url($publicUrl, PHP_URL_PATH);

        $this->get('http://img.test' . $publicPath)
            ->assertOk();

        $this->withHeaders([
            'x-api-key' => 'test-api-key',
            'x-csrf-token' => 'csrf-token',
        ])->actingAs($user)
            ->patchJson("/api/v1/gallery/assets/{$assetId}", [
                'visibility' => 'private',
            ])->assertOk()->assertJsonFragment([
                'publicUrl' => null,
            ]);

        $this->get($publicUrl)->assertNotFound();
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

    private function seedSessionForUser(User $user, string $token): void
    {
        UserSession::create([
            'csrf_token' => $token,
            'user_id' => $user->id,
            'email' => $user->email,
            'created_at' => now(),
            'expires_at' => now()->addHour(),
        ]);
    }

    /**
     * @return array{token:string,uploadUrl:string,expiresAt:string,maxBytes:int,headers:array<string,string>,allowedMimeTypes:array<int,string>,meta:mixed,appId:int|null}
     */
    private function issueUploadTicket(User $user, array $payload = []): array
    {
        $response = $this->withHeaders([
            'x-api-key' => 'test-api-key',
            'x-csrf-token' => 'csrf-token',
        ])->actingAs($user)
            ->postJson('/api/v1/gallery/assets/upload-ticket', $payload);

        $response->assertOk();

        $json = $response->json();

        return [
            'token' => Arr::get($json, 'token'),
            'uploadUrl' => Arr::get($json, 'uploadUrl'),
            'expiresAt' => Arr::get($json, 'expiresAt'),
            'maxBytes' => Arr::get($json, 'maxBytes'),
            'allowedMimeTypes' => Arr::get($json, 'allowedMimeTypes', []),
            'headers' => Arr::get($json, 'headers', []),
            'meta' => Arr::get($json, 'meta'),
            'appId' => Arr::get($json, 'appId'),
        ];
    }

    private function ensureCurrency(string $code = 'JPY'): void
    {
        DB::table('currencies')->updateOrInsert(
            ['code' => $code],
            [
                'name' => 'Japanese Yen',
                'symbol' => 'JPY',
                'symbol_native' => 'JPY',
                'minor_unit' => 0,
                'rounding' => 0,
                'name_plural' => 'Japanese yen',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function createAppForUser(User $user, string $currency = 'JPY'): App
    {
        $this->ensureCurrency($currency);

        $app = App::create([
            'app_key' => (string) Str::ulid(),
            'name' => 'Test App ' . Str::random(4),
            'url' => null,
            'description' => null,
            'currency_code' => $currency,
            'date_update_time' => '00:00',
            'sync_update_time' => false,
            'pity_system' => false,
            'guarantee_count' => 0,
            'rarity_defs' => null,
            'marker_defs' => null,
            'task_defs' => null,
        ]);

        UserApp::create([
            'user_id' => $user->id,
            'app_id' => $app->id,
        ]);

        return $app;
    }
}

