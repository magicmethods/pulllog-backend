<?php

namespace App\Providers;

use App\Models\GalleryAsset;
use App\Policies\GalleryAssetPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(GalleryAsset::class, GalleryAssetPolicy::class);
    }
}
