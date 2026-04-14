<?php

namespace Spatie\MediaLibrary\Support;

use Spatie\Image\Drivers\ImageDriver;
use Spatie\Image\Image;

class ImageFactory
{
    public static function load(string $path): ImageDriver
    {
        $driver = config('media-library.image_driver');

        // Fall back to imagick for animated GIFs (VIPS doesn't support multi-frame)
        if ($driver === 'vips' && self::isAnimatedGif($path)) {
            $driver = 'imagick';
        }

        return Image::useImageDriver($driver)
            ->loadFile($path);
    }

    protected static function isAnimatedGif(string $path): bool
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'gif') {
            return false;
        }

        $content = file_get_contents($path);

        return $content !== false && substr_count($content, "\x00\x21\xF9") > 1;
    }
}
