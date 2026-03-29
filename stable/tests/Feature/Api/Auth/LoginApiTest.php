<?php

namespace Tests\Feature\Api\Auth;

use App\Models\AuthToken;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginApiTest extends TestCase
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
        ]);
    }

    public function test_login_and_autologin_flow(): void
    {
        $plan = Plan::create([
            'name' => 'Basic',
            'description' => 'test plan',
            'max_apps' => 5,
            'max_app_name_length' => 30,
            'max_app_desc_length' => 400,
            'max_log_tags' => 5,
            'max_log_tag_length' => 22,
            'max_log_text_length' => 250,
            'max_logs_per_app' => -1,
            'max_gallery_mb' => 300,
            'max_upload_mb_per_file' => 20,
            'external_storage_allowed' => false,
            'transcode_webp' => true,
            'max_storage_mb' => 500,
            'price_per_month' => 0,
            'is_active' => true,
        ]);

        $password = 'password123';
        $user = User::factory()->create([
            'password' => Hash::make($password),
            'plan_id' => $plan->id,
            'plan_expiration' => now()->addYear(),
            'roles' => ['user'],
            'is_verified' => true,
            'is_deleted' => false,
            'language' => 'en',
            'theme' => 'light',
            'home_page' => '/apps',
        ]);

        $loginResponse = $this->withHeaders([
            'x-api-key' => 'test-api-key',
        ])->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
            'remember' => true,
        ]);

        $loginResponse
            ->assertOk()
            ->assertJson([
                'state' => 'success',
            ]);

        $rememberToken = $loginResponse->json('rememberToken');
        $this->assertNotNull($rememberToken);
        $this->assertDatabaseHas('auth_tokens', [
            'user_id' => $user->id,
            'type' => 'remember',
        ]);

        $autoResponse = $this->withHeaders([
            'x-api-key' => 'test-api-key',
        ])->postJson('/api/v1/auth/autologin', [
            'remember_token' => $rememberToken,
        ]);

        $autoResponse
            ->assertOk()
            ->assertJson([
                'state' => 'success',
            ]);

        $this->assertNotNull($autoResponse->json('csrfToken'));
        $this->assertNotNull($autoResponse->json('rememberToken'));
    }

    public function test_dummy_endpoint_is_accessible(): void
    {
        $this->getJson('/api/v1/dummy')
            ->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);
    }
}
