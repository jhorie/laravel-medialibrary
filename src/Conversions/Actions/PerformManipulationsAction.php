<?php

namespace Spatie\MediaLibrary\Conversions\Actions;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Image\Exceptions\UnsupportedImageFormat;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\ImageFactory;

class PerformManipulationsAction
{
    public function execute(
        Media $media,
        Conversion $conversion,
        string $imageFile,
    ): string {

        if ($conversion->getManipulations()->isEmpty()) {
            return $imageFile;
        }

        if (! File::exists($imageFile)) {
            return '';
        }

        $conversionTempFile = $this->getConversionTempFileName($media, $conversion, $imageFile);

        File::copy($imageFile, $conversionTempFile);

        $supportedFormats = ['jpg', 'jpeg', 'pjpg', 'png', 'gif', 'webp'];
        if ($conversion->shouldKeepOriginalImageFormat() && in_array($media->extension, $supportedFormats)) {
            $conversion->format($media->extension);
        }

        $targetFormat = $conversion->getManipulations()->getManipulationArgument('format');
        if (Str::startsWith(File::mimeType($conversionTempFile), "video/") && in_array($targetFormat, [["mp4"], ["webm"]])) {
            $format = $targetFormat[0];
            $input = escapeshellarg($conversionTempFile);
            $output = escapeshellarg($conversionTempFile . '.' . $format);

            $codecArgs = $format === 'webm'
                ? "-c:v libvpx-vp9 -b:v 0 -crf 32 -row-mt 1"
                : "-c:v libx264 -preset fast -crf 28 -movflags +faststart";

            exec("ffmpeg -i {$input} {$codecArgs} -vf \"scale='min(1080,iw)':-2\" -an {$output} 2>&1", $cmdOutput, $returnCode);

            if ($returnCode === 0 && File::exists($conversionTempFile . '.' . $format)) {
                unlink($conversionTempFile);
                rename($conversionTempFile . '.' . $format, $conversionTempFile);
            } else {
                throw new \RuntimeException("FFmpeg conversion failed (code {$returnCode}): " . implode("\n", $cmdOutput));
            }
        } else {
        $image = ImageFactory::load($conversionTempFile)
            ->format('jpg');

        try {
            $conversion->getManipulations()->apply($image);

            $image->save();
        } catch (UnsupportedImageFormat) {

        }
        }

        return $conversionTempFile;
    }

    protected function getConversionTempFileName(
        Media $media,
        Conversion $conversion,
        string $imageFile,
    ): string {
        $directory = pathinfo($imageFile, PATHINFO_DIRNAME);

        $extension = $media->extension;

        if ($extension === '') {
            $extension = 'jpg';
        }

        $fileName = Str::random(32)."{$conversion->getName()}.{$extension}";

        return "{$directory}/{$fileName}";
    }
}
