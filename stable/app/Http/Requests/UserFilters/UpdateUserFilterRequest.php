<?php

namespace App\Http\Requests\UserFilters;

use App\Services\LocaleResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UpdateUserFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $context = (string) $this->route('context');
        $tileSizes = config('default.user_filters.tile_sizes', []);
        $allowedTileIds = config("default.user_filters.tile_ids.$context", []);

        $layoutIdRule = ['required', 'string'];
        if (!empty($allowedTileIds)) {
            $layoutIdRule[] = Rule::in($allowedTileIds);
        }

        return [
            'version' => ['required', 'string', 'max:20'],
            'layout' => ['required', 'array', 'min:1'],
            'layout.*.id' => $layoutIdRule,
            'layout.*.size' => ['required', 'string', Rule::in($tileSizes)],
            'layout.*.visible' => ['required', 'boolean'],
            'layout.*.locked' => ['nullable', 'boolean'],
            'layout.*.order' => ['required', 'integer', 'min:0'],
            'filters' => ['sometimes', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'layout.*.id.in' => 'Invalid tile id.',
            'layout.*.size.in' => 'Invalid tile size.',
        ];
    }

    public function payload(): array
    {
        $validated = $this->validated();

        $normalizedLayout = [];
        foreach (array_values($validated['layout']) as $index => $item) {
            $normalizedLayout[] = [
                'id' => $item['id'],
                'size' => $item['size'],
                'visible' => (bool) ($item['visible'] ?? false),
                'locked' => (bool) ($item['locked'] ?? false),
                'order' => (int) ($item['order'] ?? ($index + 1)),
            ];
        }

        $validated['layout'] = $normalizedLayout;
        $validated['filters'] = $validated['filters'] ?? [];

        return $validated;
    }

    protected function failedValidation(Validator $validator): void
    {
        Log::info('user_filters.validation_failed', [
            'user_id' => optional($this->user())->id,
            'context' => (string) $this->route('context'),
            'errors' => $validator->errors()->all(),
        ]);

        $lang = LocaleResolver::resolve($this, $this->user());

        throw new HttpResponseException(response()->json([
            'state' => 'error',
            'message' => trans('auth.validation_failed', ['error' => $validator->errors()->first()], $lang),
        ], 422));
    }
}
