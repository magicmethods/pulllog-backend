<?php

namespace Tests\Feature\Gallery;

use App\Models\App;
use App\Models\GalleryUploadTicket;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserSession;
use App\Models\UserApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Tests\TestCase;

class GalleryUploadTicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'demo.demo_user_ids' => [],
            'demo.demo_email' => null,

            'api.api_key' => 'test-api-key',
            'api.base_uri' => 'v1',
            'gallery.upload_ticket_ttl' => 60,
            'gallery.disk' => 'private',
            'gallery.public_host' => 'http://img.test',
        ]);
    }

    public function test_user_can_issue_upload_ticket(): void
    {
        $plan = $this->createPlan();
        $user = $this->createUserForPlan($plan->id);
        $app = $this->createAppForUser($user);
        $this->seedSessionForUser($user, 'csrf-token');
        DB::table('gallery_usage_stats')->insert([
            'user_id' => $user->id,
            'bytes_used' => 0,
            'files_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $file = UploadedFile::fake()->image('ticket.jpg', 600, 600)->size(300);

        $payload = [
            'fileName' => 'ticket.jpg',
            'expectedBytes' => $file->getSize(),
            'mime' => $file->getMimeType(),
            'visibility' => 'private',
            'tags' => ['test'],
            'appKey' => $app->app_key,
        ];

        $response = $this->withHeaders([
            'x-api-key' => 'test-api-key',
            'x-csrf-token' => 'csrf-token',
        ])->actingAs($user)
            ->postJson('/api/v1/gallery/assets/upload-ticket', $payload);

        $response->assertOk();
        $response->assertJsonStructure([
            'uploadUrl',
            'token',
            'expiresAt',
            'maxBytes',
            'allowedMimeTypes',
            'headers' => ['x-upload-token'],
            'meta',
            'appId',
        ]);

        $token = $response->json('token');
        $this->assertNotEmpty($token);

        $stored = GalleryUploadTicket::where('token', $token)->first();
        $this->assertNotNull($stored);
        $this->assertSame($user->id, (int) $stored->user_id);
        $this->assertSame($file->getMimeType(), $stored->mime);
        $this->assertSame('ticket.jpg', $stored->file_name);
        $this->assertSame('private', $stored->visibility);
        $this->assertSame(['test'], $stored->tags);
        $this->assertSame($app->id, $stored->app_id);
        $this->assertSame($app->id, $response->json('appId'));
        $this->assertSame($app->app_key, Arr::get($response->json('meta', []), 'appKey'));
        $this->assertTrue($stored->expires_at->greaterThan(now()));
    }

    public function test_upload_ticket_requests_are_rate_limited(): void
    {
        $plan = $this->createPlan();
        $user = $this->createUserForPlan($plan->id);
        $app = $this->createAppForUser($user);
        $this->seedSessionForUser($user, 'csrf-token');
        DB::table('gallery_usage_stats')->insert([
            'user_id' => $user->id,
            'bytes_used' => 0,
            'files_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $headers = [
            'x-api-key' => 'test-api-key',
            'x-csrf-token' => 'csrf-token',
        ];

        for ($i = 0; $i < 10; $i++) {
            $this->withHeaders($headers)->actingAs($user)
                ->postJson('/api/v1/gallery/assets/upload-ticket', [
                    'expectedBytes' => 256000,
                    'mime' => 'image/jpeg',
                    'appKey' => $app->app_key,
                ])
                ->assertOk();
        }

        $this->withHeaders($headers)->actingAs($user)
            ->postJson('/api/v1/gallery/assets/upload-ticket', [
                'expectedBytes' => 256000,
                'mime' => 'image/jpeg',
                'appKey' => $app->app_key,
            ])
            ->assertStatus(429);
    }

    public function test_upload_ticket_still_requires_api_key(): void
    {
        $plan = $this->createPlan();
        $user = $this->createUserForPlan($plan->id);
        $app = $this->createAppForUser($user);
        $this->seedSessionForUser($user, 'csrf-token');
        DB::table('gallery_usage_stats')->insert([
            'user_id' => $user->id,
            'bytes_used' => 0,
            'files_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeaders([
            'x-csrf-token' => 'csrf-token',
        ])->postJson('/api/v1/gallery/assets/upload-ticket', [
            'expectedBytes' => 256000,
            'mime' => 'image/jpeg',
            'appKey' => $app->app_key,
        ])->assertStatus(401)->assertJson([
            'message' => 'Unauthorized',
        ]);
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
            'name' => 'Ticket App ' . Str::random(4),
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
