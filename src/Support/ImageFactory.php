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

        // Fall back to imagick for PNGs (VIPS palette/alpha handling can be lossy)
        if ($driver === 'vips' && self::isPng($path)) {
            $driver = 'imagick';
        }

        return Image::useImageDriver($driver)
            ->loadFile($path);
    }

    public static function isAnimatedGif(string $path): bool
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'gif') {
            return false;
        }

        $content = file_get_contents($path);

        return $content !== false && substr_count($content, "\x00\x21\xF9") > 1;
    }

    public static function isPng(string $path): bool
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'png') {
            return true;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $signature = fread($handle, 8);
        fclose($handle);

        return $signature === "\x89PNG\r\n\x1a\n";
    }
}
