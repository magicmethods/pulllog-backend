<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserFilterResource extends JsonResource
{
    /**
     * @param Request $request
     */
    public function toArray($request): array
    {
        $data = is_array($this->resource)
            ? $this->resource
            : [
                'context' => $this->context,
                'version' => $this->version,
                'layout' => $this->layout ?? [],
                'filters' => $this->filters ?? [],
                'created' => true,
                'updatedAt' => optional($this->updated_at)->toISOString(),
            ];

        $layoutItems = $data['layout'] ?? [];
        $layout = [];
        foreach (array_values($layoutItems) as $index => $item) {
            $layout[] = [
                'id' => $item['id'],
                'size' => $item['size'],
                'visible' => (bool) ($item['visible'] ?? false),
                'locked' => (bool) ($item['locked'] ?? false),
                'order' => (int) ($item['order'] ?? ($index + 1)),
            ];
        }

        $filters = $data['filters'] ?? [];
        if (is_array($filters) && empty($filters)) {
            $filters = (object) [];
        }

        return [
            'context' => $data['context'],
            'version' => $data['version'],
            'layout' => $layout,
            'filters' => $filters,
            'created' => (bool) ($data['created'] ?? true),
            'updatedAt' => $data['updatedAt'] ?? null,
        ];
    }
}