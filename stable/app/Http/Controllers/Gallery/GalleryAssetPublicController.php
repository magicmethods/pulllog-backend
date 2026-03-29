<?php

namespace App\Http\Controllers\Gallery;

use App\Http\Controllers\Controller;
use App\Models\GalleryAssetLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GalleryAssetPublicController extends Controller
{
    public function show(Request $request, string $code)
    {
        $link = GalleryAssetLink::with('asset')->where('code', $code)->first();

        if (!$link || !$link->asset || $link->asset->visibility !== 'public') {
            abort(404);
        }

        if ($link->expire_at && $link->expire_at->isPast()) {
            abort(404);
        }

        $asset = $link->asset;
        $disk = $asset->disk ?? config('gallery.disk');
        $path = $asset->path;

        $filesystem = Storage::disk($disk);

        if (!$filesystem->exists($path)) {
            abort(404);
        }

        $headers = [
            'Cache-Control' => 'public, max-age=604800',
            'ETag' => $asset->hash_sha256,
        ];

        $response = $filesystem->response($path, null, $headers);

        DB::table('gallery_asset_links')
            ->where('id', $link->id)
            ->update([
                'last_accessed_at' => now(),
                'hit_count' => DB::raw('hit_count + 1'),
                'updated_at' => now(),
            ]);

        return $response;
    }
}
