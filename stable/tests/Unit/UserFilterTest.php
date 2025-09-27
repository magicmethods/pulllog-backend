<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_and_filters_are_cast_to_array(): void
    {
        $userFilter = UserFilter::factory()->create([
            'layout' => [[
                'id' => 'expense-ratio',
                'size' => 'span-2',
                'visible' => true,
                'locked' => false,
                'order' => 1,
            ]],
            'filters' => ['status' => 'all'],
        ]);

        $this->assertIsArray($userFilter->layout);
        $this->assertIsArray($userFilter->filters);
        $this->assertSame('expense-ratio', $userFilter->layout[0]['id']);
        $this->assertSame('all', $userFilter->filters['status']);
    }

    public function test_user_relation_returns_parent(): void
    {
        $userFilter = UserFilter::factory()->create();

        $this->assertInstanceOf(User::class, $userFilter->user);
    }
}