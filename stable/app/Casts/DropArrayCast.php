<?php

namespace App\Casts;

use App\ValueObjects\Drop;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class DropArrayCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) return [];
        $array = json_decode($value, true) ?? [];
        return array_map(fn($item) => Drop::fromArray($item), $array);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        $array = array_map(function($item) {
            if ($item instanceof Drop) {
                return $item->toArray();
            }
            if (is_array($item)) {
                return $item;
            }
            return [];
        }, $value ?? []);
        return json_encode($array);
    }
}
