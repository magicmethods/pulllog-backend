<?php

namespace Database\Factories;

use App\Enums\FilterContext;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserFilter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserFilter>
 */
class UserFilterFactory extends Factory
{
    protected $model = UserFilter::class;

    public function definition(): array
    {
        return [
            'user_id' => function () {
                $plan = Plan::firstOrCreate(
                    ['name' => 'factory-basic'],
                    [
                        'description' => 'Factory plan',
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

                return User::factory()
                    ->state([
                        'roles' => [],
                        'plan_id' => $plan->id,
                        'plan_expiration' => now()->addMonth(),
                        'language' => 'ja',
                        'theme' => 'light',
                        'home_page' => '/apps',
                        'unread_notices' => [],
                    ])
                    ->create()
                    ->id;
            },
            'context' => $this->faker->randomElement(FilterContext::values()),
            'version' => 'v1',
            'layout' => [[
                'id' => 'expense-ratio',
                'size' => 'span-3',
                'visible' => true,
                'locked' => false,
                'order' => 0,
            ]],
            'filters' => [],
        ];
    }
}