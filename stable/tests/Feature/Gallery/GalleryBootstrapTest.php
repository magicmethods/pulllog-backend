<?php

namespace Tests\Feature\Gallery;

use App\Models\App;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserApp;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class GalleryBootstrapTest extends TestCase
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

    public function test_bootstrap_returns_assets_and_usage(): void
    {
        $plan = $this->createPlan();
        $user = $this->createUserForPlan($plan->id);
        DB::table('gallery_usage_stats')->insert([
            'user_id' => $user->id,
            'bytes_used' => 1024,
            'files_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedSessionForUser($user, 'csrf-token');

        $response = $this->withHeaders([
            'x-api-key' => 'test-api-key',
            'x-csrf-token' => 'csrf-token',
        ])->actingAs($user)
            ->getJson('/api/v1/gallery/bootstrap');

        $response->assertOk();

        $json = $response->json();

        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('links', $json);
        $this->assertArrayHasKey('meta', $json);

        $this->assertArrayHasKey('assets', $json['data']);
        $this->assertIsArray($json['data']['assets']);

        $this->assertArrayHasKey('usage', $json['data']);
        $usage = $json['data']['usage'];
        $this->assertArrayHasKey('usedBytes', $usage);
        $this->assertArrayHasKey('maxBytes', $usage);
        $this->assertArrayHasKey('remainingBytes', $usage);
        $this->assertArrayHasKey('filesCount', $usage);
    }

    public function test_bootstrap_requires_api_key(): void
    {
        $this->getJson('/api/v1/gallery/bootstrap')
            ->assertStatus(401);
    }

    public function test_bootstrap_requires_csrf(): void
    {
        $this->withHeaders([
            'x-api-key' => 'test-api-key',
        ])->getJson('/api/v1/gallery/bootstrap')
            ->assertStatus(419);
    }

    public function test_bootstrap_returns_504_when_hard_timeout_exceeded(): void
    {
        config(['gallery.bootstrap_hard_timeout_ms' => 0]);

        $plan = $this->createPlan();
        $user = $this->createUserForPlan($plan->id);
        DB::table('gallery_usage_stats')->insert([
            'user_id' => $user->id,
            'bytes_used' => 0,
            'files_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedSessionForUser($user, 'csrf-token');

        $this->withHeaders([
            'x-api-key' => 'test-api-key',
            'x-csrf-token' => 'csrf-token',
        ])->actingAs($user)
            ->getJson('/api/v1/gallery/bootstrap')
            ->assertStatus(504);
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
}
