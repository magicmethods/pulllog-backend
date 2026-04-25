<?php

namespace Tests\Feature\Gallery;

use App\Models\App;
use App\Models\GalleryAsset;
use App\Models\GalleryUploadTicket;
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
    public function test_user_can_upload_gallery_asset_without_api_key(): void
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
            'x-csrf-token' => 'csrf-token',
            'x-upload-token' => $ticket['token'],
        ])
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

    public function test_upload_with_invalid_token_returns_401_without_api_key(): void
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

        $file = UploadedFile::fake()->image('invalid-token.jpg', 800, 600)->size(400);

        $this->withHeaders([
            'x-csrf-token' => 'csrf-token',
            'x-upload-token' => 'invalid-token',
        ])->actingAs($user)
            ->postJson('/api/v1/gallery/assets', [
            'file' => $file,
            'app_key' => $app->app_key,
        ])->assertStatus(401)->assertJson([
            'message' => 'Invalid upload token',
        ]);
    }

    public function test_upload_with_expired_token_returns_401_without_api_key(): void
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

        $file = UploadedFile::fake()->image('expired-token.jpg', 800, 600)->size(400);
        $ticket = $this->createStoredUploadTicket($user, [
            'expires_at' => now()->subMinute(),
            'mime' => $file->getMimeType(),
        ]);

        $this->withHeaders([
            'x-csrf-token' => 'csrf-token',
            'x-upload-token' => $ticket->token,
        ])->actingAs($user)
            ->postJson('/api/v1/gallery/assets', [
            'file' => $file,
            'app_key' => $app->app_key,
        ])->assertStatus(401)->assertJson([
            'message' => 'Upload token expired',
        ]);
    }

    public function test_upload_with_used_token_returns_401_without_api_key(): void
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

        $file = UploadedFile::fake()->image('used-token.jpg', 800, 600)->size(400);
        $ticket = $this->createStoredUploadTicket($user, [
            'used_at' => now()->subSecond(),
            'mime' => $file->getMimeType(),
        ]);

        $this->withHeaders([
            'x-csrf-token' => 'csrf-token',
            'x-upload-token' => $ticket->token,
        ])->actingAs($user)
            ->postJson('/api/v1/gallery/assets', [
            'file' => $file,
            'app_key' => $app->app_key,
        ])->assertStatus(401)->assertJson([
            'message' => 'Upload token already used',
        ]);
    }

    public function test_upload_without_csrf_is_rejected_even_with_valid_token(): void
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

        $file = UploadedFile::fake()->image('missing-csrf.jpg', 800, 600)->size(400);
        $ticket = $this->createStoredUploadTicket($user, [
            'app_id' => $app->id,
            'expected_bytes' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ]);

        $this->withHeaders([
            'x-upload-token' => $ticket->token,
        ])->postJson('/api/v1/gallery/assets', [
            'file' => $file,
            'app_key' => $app->app_key,
        ])->assertStatus(419);
    }

    public function test_reuploading_a_soft_deleted_duplicate_restores_the_asset(): void
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

        $file = UploadedFile::fake()->image('restore-duplicate.jpg', 800, 600)->size(400);
        $firstTicket = $this->issueUploadTicket($user, [
            'appKey' => $app->app_key,
            'expectedBytes' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ]);

        $firstResponse = $this->withHeaders([
            'x-csrf-token' => 'csrf-token',
            'x-upload-token' => $firstTicket['token'],
        ])->postJson('/api/v1/gallery/assets', [
            'file' => $file,
            'app_key' => $app->app_key,
            'title' => 'Original title',
        ]);

        $firstResponse->assertCreated();

        $asset = GalleryAsset::firstOrFail();
        $asset->delete();

        $restoredUpload = new UploadedFile(
            $file->getPathname(),
            'restore-duplicate.jpg',
            $file->getMimeType(),
            null,
            true,
        );

        $secondTicket = $this->issueUploadTicket($user, [
            'appKey' => $app->app_key,
            'expectedBytes' => $restoredUpload->getSize(),
            'mime' => $restoredUpload->getMimeType(),
        ]);

        $secondResponse = $this->withHeaders([
            'x-csrf-token' => 'csrf-token',
            'x-upload-token' => $secondTicket['token'],
        ])->postJson('/api/v1/gallery/assets', [
            'file' => $restoredUpload,
            'app_key' => $app->app_key,
            'title' => 'Restored title',
            'description' => 'Restored description',
        ]);

        $secondResponse->assertCreated();

        $restoredAsset = GalleryAsset::query()->firstOrFail();
        $this->assertSame($asset->id, $restoredAsset->id);
        $this->assertNull($restoredAsset->deleted_at);
        $this->assertSame('Restored title', $restoredAsset->title);
        $this->assertSame('Restored description', $restoredAsset->description);
        $this->assertSame(1, GalleryAsset::count());
    }

    public function test_demo_guard_blocks_upload_without_api_key(): void
    {
        Storage::fake('private');

        $plan = $this->createPlan();
        $user = $this->createUserForPlan($plan->id, ['demo']);
        $app = $this->createAppForUser($user);
        DB::table('gallery_usage_stats')->insert([
            'user_id' => $user->id,
            'bytes_used' => 0,
            'files_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedSessionForUser($user, 'csrf-token');

        $file = UploadedFile::fake()->image('demo-blocked.jpg', 800, 600)->size(400);
        $ticket = $this->createStoredUploadTicket($user, [
            'mime' => $file->getMimeType(),
        ]);

        $response = $this->withHeaders([
            'x-csrf-token' => 'csrf-token',
            'x-upload-token' => $ticket->token,
        ])->actingAs($user)
            ->postJson('/api/v1/gallery/assets', [
            'file' => $file,
            'app_key' => $app->app_key,
        ]);

        $response->assertNoContent();
        $response->assertHeader('X-Demo-Blocked', 'write-operation');
        $this->assertSame(0, GalleryAsset::count());
    }

    public function test_direct_upload_preflight_allows_upload_token_and_credentials(): void
    {
        config([
            'cors.allowed_origins' => ['https://pull.log:4649'],
            'cors.allowed_headers' => ['Origin', 'X-Requested-With', 'Content-Type', 'Accept', 'x-csrf-token', 'x-upload-token'],
            'cors.supports_credentials' => true,
        ]);

        $response = $this->withHeaders([
            'Origin' => 'https://pull.log:4649',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'x-csrf-token, x-upload-token',
        ])->call('OPTIONS', '/api/v1/gallery/assets');

        $this->assertContains($response->getStatusCode(), [200, 204]);
        $this->assertSame('https://pull.log:4649', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
        $this->assertContains('x-upload-token', array_map(
            static fn (string $header): string => strtolower($header),
            config('cors.allowed_headers', [])
        ));
    }

    public function test_gallery_assets_index_requires_authentication_headers(): void
    {
        $this->getJson('/api/v1/gallery/assets')
            ->assertStatus(401);

        $this->withHeaders([
            'x-api-key' => 'test-api-key',
        ])->getJson('/api/v1/gallery/assets')
            ->assertStatus(419);
    }

    public function test_gallery_usage_show_requires_authentication_headers(): void
    {
        $this->getJson('/api/v1/gallery/usage')
            ->assertStatus(401);

        $this->withHeaders([
            'x-api-key' => 'test-api-key',
        ])->getJson('/api/v1/gallery/usage')
            ->assertStatus(419);
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

    private function createUserForPlan(int $planId, array $roles = ['user']): User
    {
        return User::factory()->create([
            'plan_id' => $planId,
            'roles' => $roles,
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

    private function createStoredUploadTicket(User $user, array $overrides = []): GalleryUploadTicket
    {
        return GalleryUploadTicket::create(array_merge([
            'user_id' => $user->id,
            'token' => (string) Str::ulid(),
            'app_id' => null,
            'file_name' => 'upload.jpg',
            'expected_bytes' => 256000,
            'mime' => 'image/jpeg',
            'max_bytes' => 5_000_000,
            'visibility' => 'private',
            'log_id' => null,
            'tags' => null,
            'meta' => null,
            'expires_at' => now()->addMinute(),
            'used_at' => null,
        ], $overrides));
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

