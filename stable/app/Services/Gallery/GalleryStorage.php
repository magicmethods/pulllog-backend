<?php

namespace App\Services\Gallery;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

class GalleryStorage
{
    public function __construct(private ?ImageManager $imageManager = null)
    {
        $this->imageManager = $this->imageManager ?? new ImageManager(new Driver());
    }

    /**
     * @return array{
     *     path:string,
     *     width:int,
     *     height:int,
     *     bytes:int,
     *     small?:array{path:string,bytes:int},
     *     large?:array{path:string,bytes:int}
     * }
     */
    public function saveWithThumbnails(UploadedFile $file, string $disk, string $baseDir): array
    {
        $ym = date('Y/m');
        $root = trim($baseDir, '/');

        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $name = uniqid('img_', true) . '.' . $ext;
        $dir = "$root/$ym";

        $path = $file->storeAs($dir, $name, $disk);

        $image = $this->imageManager->read($file->getRealPath());
        $width = $image->width();
        $height = $image->height();
        $bytesOriginal = Storage::disk($disk)->size($path);

        $small = $this->makeThumbnail($image, config('gallery.thumb.small'));
        $smallName = uniqid('s_', true) . '.' . $ext;
        $smallDir = "$root/$ym/thumbs";
        $smallPath = "$smallDir/$smallName";
        Storage::disk($disk)->put($smallPath, $small['binary']);
        $bytesSmall = Storage::disk($disk)->size($smallPath);

        $large = $this->makeThumbnail($image, config('gallery.thumb.large'));
        $largeName = uniqid('l_', true) . '.' . $ext;
        $largeDir = "$root/$ym/thumbs";
        $largePath = "$largeDir/$largeName";
        Storage::disk($disk)->put($largePath, $large['binary']);
        $bytesLarge = Storage::disk($disk)->size($largePath);

        return [
            'path' => $path,
            'width' => $width,
            'height' => $height,
            'bytes' => $bytesOriginal,
            'small' => ['path' => $smallPath, 'bytes' => $bytesSmall],
            'large' => ['path' => $largePath, 'bytes' => $bytesLarge],
        ];
    }

    /**
     * @param array{max:int,quality:int} $config
     * @return array{binary:string,image:ImageInterface}
     */
    private function makeThumbnail(ImageInterface $image, array $config): array
    {
        $thumb = clone $image;
        $thumb->scaleDown($config['max']);
        $binary = (string) $thumb->toJpeg($config['quality']);

        return [
            'binary' => $binary,
            'image' => $thumb,
        ];
    }
}