<?php

namespace App\Enums;

enum FilterContext: string
{
    case Stats = 'stats';
    case Apps = 'apps';
    case History = 'history';
    case Gallery = 'gallery';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $context): string => $context->value,
            self::cases()
        );
    }
}