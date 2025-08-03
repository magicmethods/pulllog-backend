<?php

namespace App\Casts;

use App\Enums\TokenType;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class TokenTypeCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?TokenType
    {
        return $value !== null ? TokenType::from($value) : null;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value instanceof TokenType) {
            return $value->value;
        }
        if (is_string($value)) {
            return TokenType::from($value)->value;
        }
        return null;
    }
}
