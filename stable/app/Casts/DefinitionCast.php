<?php

namespace App\Casts;

use App\ValueObjects\Definition;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class DefinitionCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        // DBからはjson文字列 or null想定（jsonカラム前提。text配列なら個別実装）
        if ($value === null) return [];
        $array = json_decode($value, true) ?? [];
        return array_map(fn($item) => Definition::fromArray($item), $array);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        // $valueはValueObject配列 or 配列の配列
        $array = array_map(function($item) {
            if ($item instanceof Definition) {
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
