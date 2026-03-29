<?php

namespace Database\Factories;

use App\Models\GalleryAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GalleryAssetFactory extends Factory
{
    protected $model = GalleryAsset::class;

    public function definition(): array
    {
        $bytes = $this->faker->numberBetween(80_000, 1_500_000);
        $thumbSmall = (int) round($bytes * 0.08);
        $thumbLarge = (int) round($bytes * 0.2);
        $ym = now()->format('Y/m');

        return [
            'user_id' => 1,
            'log_id' => null,
            'disk' => 'local',
            'path' => "gallery/{$ym}/" . Str::uuid() . '.jpg',
            'thumb_path_small' => "gallery/{$ym}/thumbs/s_" . Str::uuid() . '.jpg',
            'thumb_path_large' => "gallery/{$ym}/thumbs/l_" . Str::uuid() . '.jpg',
            'mime' => 'image/jpeg',
            'bytes' => $bytes,
            'bytes_thumb_small' => $thumbSmall,
            'bytes_thumb_large' => $thumbLarge,
            'width' => 1920,
            'height' => 1080,
            'hash_sha256' => $this->faker->sha256(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(10),
            'tags' => $this->faker->randomElements(['daily', 'highlight', 'note', 'mvp'], 2),
            'visibility' => 'private',
        ];
    }
}
