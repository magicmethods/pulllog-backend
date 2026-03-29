<?php

namespace App\Services\Gallery;

use App\Models\GalleryAsset;
use App\Models\GalleryAssetLink;
use Illuminate\Support\Str;

class GalleryAssetLinkService
{
    public function createOrRefreshLink(GalleryAsset $asset): GalleryAssetLink
    {
        $link = $asset->link;

        if ($link) {
            return $link;
        }

        $code = $this->generateUniqueCode();

        return GalleryAssetLink::create([
            'asset_id' => $asset->id,
            'code' => $code,
        ]);
    }

    public function deleteLink(?GalleryAsset $asset): void
    {
        if (!$asset) {
            return;
        }

        $asset->link()->delete();
    }

    protected function generateUniqueCode(): string
    {
        do {
            $code = Str::lower(Str::random(10));
        } while (GalleryAssetLink::where('code', $code)->exists());

        return $code;
    }
}
