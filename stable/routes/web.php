<?php

use App\Http\Controllers\Gallery\GalleryAssetPublicController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

$hosts = array_filter(array_unique([
    parse_url(config('gallery.public_host'), PHP_URL_HOST),
    'img.test',
]));

foreach (array_values($hosts) as $index => $host) {
    Route::domain($host)
        ->middleware('web')
        ->group(function () use ($index, $host) {
            Route::get('{code}', [GalleryAssetPublicController::class, 'show'])
                ->where('code', '[A-Za-z0-9]+')
                ->name($index === 0 ? 'gallery.public.show' : 'gallery.public.show.' . $host);
        });
}
