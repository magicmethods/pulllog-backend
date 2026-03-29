<?php

namespace App\Http\Requests\Gallery;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGalleryAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:2000',
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'string|max:50',
            'visibility' => 'nullable|in:private,unlisted,public',
            'log_id' => 'nullable|integer|exists:logs,id',
        ];
    }
}
