<?php

namespace Tests\Feature\UserFilters;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserFilter;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UserFiltersApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['api.api_key' => 'test-key']);
    }

    public function test_show_returns_defaults_when_not_created(): void
    {
        $user = $this->createUser();
        $headers = $this->headersFor($user);

        $response = $this->withHeaders($headers)->getJson(route('user-filters.show', ['context' => 'stats']));
        $defaults = config('default.user_filters.defaults.stats.layout');

        $response->assertStatus(200);
        $this->assertCacheControlNoStore($response);
        $response->assertJson(fn (AssertableJson $json) => $json
            ->where('context', 'stats')
            ->where('version', config('default.user_filters.defaults.stats.version'))
            ->where('created', false)
            ->where('updatedAt', null)
            ->has('layout', count($defaults))
            ->has('filters')
            ->etc()
        );

        $body = json_decode($response->getContent());
        $this->assertIsObject($body->filters);
        $this->assertSame([], (array) $body->filters);
    }

    public function test_update_creates_user_filter_record(): void
    {
        $user = $this->createUser();
        $headers = $this->headersFor($user);

        $payload = [
            'version' => 'v1',
            'layout' => [
                [
                    'id' => 'expense-ratio',
                    'size' => 'span-2',
                    'visible' => true,
                    'locked' => false,
                    'order' => 1,
                ],
            ],
            'filters' => ['status' => 'all'],
        ];

        $response = $this->withHeaders($headers)->putJson(route('user-filters.update', ['context' => 'stats']), $payload);

        $response->assertStatus(201);
        $this->assertCacheControlNoStore($response);
        $response->assertJson(fn (AssertableJson $json) => $json
            ->where('context', 'stats')
            ->where('version', 'v1')
            ->where('created', true)
            ->whereType('layout', 'array')
            ->where('layout.0.id', 'expense-ratio')
            ->where('filters.status', 'all')
            ->whereNotNull('updatedAt')
            ->etc()
        );

        $record = UserFilter::first();
        $this->assertNotNull($record);
        $this->assertEquals($user->id, $record->user_id);
        $this->assertEquals('stats', $record->context);
        $this->assertEquals('v1', $record->version);
        $this->assertSame('expense-ratio', $record->layout[0]['id']);
        $this->assertSame('all', $record->filters['status']);
    }

    public function test_update_overwrites_existing_record(): void
    {
        $user = $this->createUser();
        $headers = $this->headersFor($user);

        $existing = UserFilter::factory()->for($user)->create([
            'context' => 'stats',
            'version' => 'v1',
            'layout' => [[
                'id' => 'expense-ratio',
                'size' => 'span-2',
                'visible' => true,
                'locked' => false,
                'order' => 1,
            ]],
            'filters' => ['status' => 'all'],
        ]);

        $payload = [
            'version' => 'v1',
            'layout' => [
                [
                    'id' => 'monthly-expense',
                    'size' => 'span-4',
                    'visible' => true,
                    'locked' => false,
                    'order' => 1,
                ],
            ],
            'filters' => ['status' => 'active'],
        ];

        $response = $this->withHeaders($headers)->putJson(route('user-filters.update', ['context' => 'stats']), $payload);

        $response->assertStatus(200);
        $this->assertCacheControlNoStore($response);
        $response->assertJson(fn (AssertableJson $json) => $json
            ->where('context', 'stats')
            ->where('version', 'v1')
            ->where('created', true)
            ->where('layout.0.id', 'monthly-expense')
            ->where('filters.status', 'active')
            ->whereNotNull('updatedAt')
            ->etc()
        );

        $existing->refresh();
        $this->assertSame('monthly-expense', $existing->layout[0]['id']);
        $this->assertSame('active', $existing->filters['status']);
    }

    public function test_update_returns_conflict_when_version_mismatch(): void
    {
        $user = $this->createUser();
        $headers = $this->headersFor($user);

        UserFilter::factory()->for($user)->create([
            'context' => 'stats',
            'version' => 'v2',
            'layout' => [[
                'id' => 'expense-ratio',
                'size' => 'span-2',
                'visible' => true,
                'locked' => false,
                'order' => 1,
            ]],
            'filters' => [],
        ]);

        $payload = [
            'version' => 'v1',
            'layout' => [
                [
                    'id' => 'expense-ratio',
                    'size' => 'span-2',
                    'visible' => true,
                    'locked' => false,
                    'order' => 1,
                ],
            ],
            'filters' => [],
        ];

        $response = $this->withHeaders($headers)->putJson(route('user-filters.update', ['context' => 'stats']), $payload);

        $response->assertStatus(409);
        $this->assertCacheControlNoStore($response);
        $response->assertJson(fn (AssertableJson $json) => $json
            ->where('state', 'error')
            ->where('message', 'layout version conflict')
            ->where('latestVersion', 'v2')
            ->has('payload.layout')
            ->has('payload.filters')
            ->etc()
        );

        $conflict = json_decode($response->getContent());
        $this->assertIsObject($conflict->payload->filters);
    }

    public function test_show_returns_not_found_for_unknown_context(): void
    {
        $user = $this->createUser();
        $headers = $this->headersFor($user);

        $response = $this->withHeaders($headers)->getJson(route('user-filters.show', ['context' => 'unknown']));

        $response->assertStatus(404);
        $this->assertCacheControlNoStore($response);
        $response->assertJson(fn (AssertableJson $json) => $json
            ->where('state', 'error')
            ->has('message')
            ->etc()
        );
    }

    public function test_update_returns_validation_error(): void
    {
        $user = $this->createUser();
        $headers = $this->headersFor($user);

        $payload = [
            'version' => 'v1',
            'layout' => [],
        ];

        $response = $this->withHeaders($headers)->putJson(route('user-filters.update', ['context' => 'stats']), $payload);

        $response->assertStatus(422);
        $this->assertCacheControlNoStore($response);
        $response->assertJson(fn (AssertableJson $json) => $json
            ->where('state', 'error')
            ->has('message')
            ->etc()
        );
    }

    private function createUser(): User
    {
        $plan = Plan::firstOrCreate(
            ['name' => 'basic'],
            [
                'description' => 'Basic testing plan',
                'max_apps' => 5,
                'max_app_name_length' => 30,
                'max_app_desc_length' => 400,
                'max_log_tags' => 5,
                'max_log_tag_length' => 22,
                'max_log_text_length' => 250,
                'max_logs_per_app' => -1,
                'max_storage_mb' => 100,
                'price_per_month' => 0,
                'is_active' => true,
            ]
        );

        return User::factory()->create([
            'roles' => [],
            'plan_id' => $plan->id,
            'plan_expiration' => now()->addMonth(),
            'language' => 'ja',
            'theme' => 'light',
            'home_page' => '/apps',
            'unread_notices' => [],
        ]);
    }

    private function headersFor(User $user): array
    {
        $token = Str::random(40);

        UserSession::create([
            'csrf_token' => $token,
            'user_id' => $user->id,
            'email' => $user->email,
            'created_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        return [
            'x-api-key' => 'test-key',
            'x-csrf-token' => $token,
        ];
    }

    private function assertCacheControlNoStore(TestResponse $response): void
    {
        $header = $response->headers->get('Cache-Control');
        $this->assertNotNull($header);

        if (!str_contains($header, 'no-store')) {
            $this->assertStringContainsString('no-cache', $header);
        }
    }
}