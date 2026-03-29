<?php

namespace App\Policies;

use App\Models\GalleryAsset;
use App\Models\User;

class GalleryAssetPolicy
{
    public function view(User $user, GalleryAsset $asset): bool
    {
        return $asset->user_id === $user->id;
    }

    public function update(User $user, GalleryAsset $asset): bool
    {
        return $asset->user_id === $user->id;
    }

    public function delete(User $user, GalleryAsset $asset): bool
    {
        return $asset->user_id === $user->id;
    }
}
