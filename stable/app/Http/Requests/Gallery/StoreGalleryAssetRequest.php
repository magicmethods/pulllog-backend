<?php

namespace App\Http\Requests\Gallery;

use App\Services\PlanLimitService;
use Illuminate\Foundation\Http\FormRequest;

class StoreGalleryAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'app_key' => $this->input('app_key', $this->input('appKey')),
        ]);
    }

    public function rules(): array
    {
        /** @var PlanLimitService $planLimitService */
        $planLimitService = app(PlanLimitService::class);
        $limits = $planLimitService->getGalleryLimitsForUser($this->user()->id);
        $maxKb = (int) floor($limits['max_upload_bytes_per_file'] / 1024);

        return [
            'file' => 'required|file|mimetypes:' . implode(',', config('gallery.allowed_mimes')) . '|max:' . $maxKb,
            'log_id' => 'nullable|integer|exists:logs,id',
            'app_key' => 'nullable|string|exists:apps,app_key',
            'title' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:2000',
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'string|max:50',
            'visibility' => 'nullable|in:private,unlisted,public',
        ];
    }
}
