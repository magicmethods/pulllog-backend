<?php

namespace App\Http\Resources;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class GalleryAssetResource extends JsonResource
{
    public function toArray($request): array
    {
        $disk = $this->disk ?? config('gallery.disk');
        /** @var FilesystemAdapter $filesystem */
        $filesystem = Storage::disk($disk);

        $ttl = (int) config('gallery.signed_url_ttl', 120);
        $needsSignedUrl = $this->visibility !== 'public';

        /**
         * @return string|null
         */
        $makeUrl = function (?string $path, string $variant) use ($filesystem, $needsSignedUrl, $ttl) {
            if (!$path) {
                return null;
            }

            if ($needsSignedUrl) {
                return URL::temporarySignedRoute(
                    'gallery.assets.content',
                    now()->addSeconds($ttl),
                    [
                        'id' => (string) $this->id,
                        'variant' => $variant,
                        'user' => (int) $this->user_id,
                    ]
                );
            }

            return null;
        };

        $app = $this->whenLoaded('app');
        $link = $this->whenLoaded('link');
        $linkModel = $link instanceof MissingValue ? null : $link;
        $publicHost = rtrim((string) config('gallery.public_host'), '/');
        $publicUrl = $linkModel?->code ? $publicHost . '/' . $linkModel->code : null;

        return [
            'id' => (string) $this->id,
            'userId' => (int) $this->user_id,
            'appId' => $this->app_id ? (int) $this->app_id : null,
            'appKey' => $app?->app_key,
            'appName' => $app?->name,
            'logId' => $this->log_id ? (int) $this->log_id : null,
            'disk' => (string) $disk,
            'path' => (string) $this->path,
            'url' => $makeUrl($this->path, 'original'),
            'publicUrl' => $publicUrl,
            'thumbSmall' => $this->thumb_path_small,
            'thumbSmallUrl' => $makeUrl($this->thumb_path_small, 'small'),
            'thumbLarge' => $this->thumb_path_large,
            'thumbLargeUrl' => $makeUrl($this->thumb_path_large, 'large'),
            'mime' => (string) $this->mime,
            'bytes' => (int) $this->bytes,
            'bytesThumbSmall' => (int) ($this->bytes_thumb_small ?? 0),
            'bytesThumbLarge' => (int) ($this->bytes_thumb_large ?? 0),
            'width' => $this->width ? (int) $this->width : null,
            'height' => $this->height ? (int) $this->height : null,
            'hashSha256' => (string) $this->hash_sha256,
            'title' => $this->title,
            'description' => $this->description,
            'tags' => $this->tags ?? [],
            'visibility' => (string) $this->visibility,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'deletedAt' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
