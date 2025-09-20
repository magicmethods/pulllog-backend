<?php

use App\Enums\FilterContext;

return [
    'user_filters' => [
        'contexts' => FilterContext::values(),
        'tile_sizes' => ['span-2', 'span-3', 'span-4', 'span-5', 'span-6'],
        'tile_ids' => [
            'stats' => [
                'expense-ratio',
                'monthly-expense',
                'cumulative-rare-rate',
                'app-pull-stats',
                'rare-breakdown',
                'rare-ranking',
            ],
            'apps' => [],
            'history' => [],
            'gallery' => [],
        ],
        'defaults' => [
            'stats' => [
                'version' => 'v1',
                'layout' => [
                    [
                        'id' => 'expense-ratio',
                        'size' => 'span-2',
                        'visible' => true,
                        'locked' => false,
                        'order' => 1,
                    ],
                    [
                        'id' => 'monthly-expense',
                        'size' => 'span-4',
                        'visible' => true,
                        'locked' => false,
                        'order' => 2,
                    ],
                    [
                        'id' => 'cumulative-rare-rate',
                        'size' => 'span-6',
                        'visible' => true,
                        'locked' => false,
                        'order' => 3,
                    ],
                    [
                        'id' => 'app-pull-stats',
                        'size' => 'span-2',
                        'visible' => true,
                        'locked' => false,
                        'order' => 4,
                    ],
                    [
                        'id' => 'rare-breakdown',
                        'size' => 'span-2',
                        'visible' => true,
                        'locked' => false,
                        'order' => 5,
                    ],
                    [
                        'id' => 'rare-ranking',
                        'size' => 'span-2',
                        'visible' => true,
                        'locked' => false,
                        'order' => 6,
                    ],
                ],
                'filters' => (object) [],
            ],
            'apps' => [
                'version' => 'v1',
                'layout' => [],
                'filters' => (object) [],
            ],
            'history' => [
                'version' => 'v1',
                'layout' => [],
                'filters' => (object) [],
            ],
            'gallery' => [
                'version' => 'v1',
                'layout' => [],
                'filters' => (object) [],
            ],
        ],
    ],
];