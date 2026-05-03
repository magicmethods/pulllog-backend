<?php

namespace App\Http\Requests\Gallery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;

class StoreUploadTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'file_name' => $this->input('fileName', $this->input('file_name')),
            'expected_bytes' => $this->input('expectedBytes', $this->input('expected_bytes')),
            'log_id' => $this->input('logId', $this->input('log_id')),
            'app_key' => $this->input('appKey', $this->input('app_key')),
        ]);
    }

    public function rules(): array
    {
        $allowedMimes = config('gallery.allowed_mimes', []);

        return [
            'file_name' => ['nullable', 'string', 'max:255'],
            'expected_bytes' => ['nullable', 'integer', 'min:1'],
            'mime' => ['nullable', 'string', Rule::in($allowedMimes)],
            'visibility' => ['nullable', 'in:private,unlisted,public'],
            'log_id' => ['nullable', 'integer', 'exists:logs,id'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
            'app_key' => ['nullable', 'string', 'exists:apps,app_key'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422)
        );
    }

    public function validatedPayload(): array
    {
        $validated = $this->validated();

        return [
            'file_name' => $validated['file_name'] ?? null,
            'expected_bytes' => isset($validated['expected_bytes']) ? (int) $validated['expected_bytes'] : null,
            'mime' => $validated['mime'] ?? null,
            'visibility' => $validated['visibility'] ?? null,
            'log_id' => isset($validated['log_id']) ? (int) $validated['log_id'] : null,
            'tags' => $validated['tags'] ?? null,
            'app_key' => $validated['app_key'] ?? null,
        ];
    }
}
