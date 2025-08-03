<?php

namespace App\Casts;

use App\Enums\Theme;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class ThemeCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Theme
    {
        return $value !== null ? Theme::from($value) : null;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value instanceof Theme) {
            return $value->value; // Get the string value of the enum
        }
        if (is_string($value)) {
            // If it's a string, ensure it matches the enum values
            return Theme::from($value)->value;
        }
        //throw new \InvalidArgumentException("Invalid value for Theme cast: " . json_encode($value));
        return null; // Allow null values to be stored as null
    }
}
