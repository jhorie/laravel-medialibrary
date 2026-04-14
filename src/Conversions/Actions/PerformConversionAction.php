<?php

namespace Spatie\MediaLibrary\Conversions\Actions;

use Illuminate\Support\Str;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;
use Spatie\MediaLibrary\Conversions\Events\ConversionWillStartEvent;
use Spatie\MediaLibrary\Conversions\ImageGenerators\ImageGeneratorFactory;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\ImageFactory;
use Spatie\MediaLibrary\ResponsiveImages\ResponsiveImageGenerator;

class PerformConversionAction
{
    public function execute(
        Conversion $conversion,
        Media $media,
        string $copiedOriginalFile
    ): void {
        $imageGenerator = ImageGeneratorFactory::forMedia($media);

        if ($conversion->shouldTouchFiles()) {
            $copiedOriginalFile = $imageGenerator->convert($copiedOriginalFile, $conversion);
        }

        if (! $copiedOriginalFile) {
            return;
        }

        $isGif = pathinfo($copiedOriginalFile, PATHINFO_EXTENSION) === "gif";
        $isAnimatedGif = $isGif && ImageFactory::isAnimatedGif($copiedOriginalFile);
        $formatArg = $conversion->getManipulations()->getManipulationArgument('format');

        $shouldUseGif2WebpConverter = config('media-library.convert_gif_to_webp_using_gif2webp')
            && $isGif
            && ($formatArg == ["webp"] || ($isAnimatedGif && $formatArg != ["gif"]));

        // Override the format to webp for animated GIFs so gif2webp can handle them
        if ($shouldUseGif2WebpConverter && $formatArg != ["webp"]) {
            $conversion->getManipulations()->format('webp');
        }

        if ($shouldUseGif2WebpConverter) {
            $conversion->setUseGif2WebpAsConverter();
        }

        event(new ConversionWillStartEvent($media, $conversion, $copiedOriginalFile));

        if ($conversion->shouldTouchFiles()) {
            $manipulationResult = (new PerformManipulationsAction())->execute($media, $conversion, $copiedOriginalFile);
        } else {
            $manipulationResult = $copiedOriginalFile;
        }

        if (! $manipulationResult) {
            return;
        }

        $newFileName = $conversion->getConversionFile($media);

        $renamedFile = $this->renameInLocalDirectory($manipulationResult, $newFileName);

        $manipulatedFile = $renamedFile;
        if ($shouldUseGif2WebpConverter) {
            $manipulatedFile = pathinfo($renamedFile, PATHINFO_DIRNAME) . '/' . (Str::random(32) . '.' . $media->extension);
            copy($renamedFile, $manipulatedFile);
            exec("gif2webp -lossy " . $renamedFile . " -o " . $renamedFile);
        }

        if ($conversion->shouldGenerateResponsiveImages()) {
            /** @var ResponsiveImageGenerator $responsiveImageGenerator */
            $responsiveImageGenerator = app(ResponsiveImageGenerator::class);

            $responsiveImageGenerator->generateResponsiveImagesForConversion(
                $media,
                $conversion,
                $manipulatedFile,
                $shouldUseGif2WebpConverter
            );
        }

        app(Filesystem::class)->copyToMediaLibrary($renamedFile, $media, 'conversions');

        $generatedExtension = pathinfo($newFileName, PATHINFO_EXTENSION);
        $media->markAsConversionGenerated($conversion->getName(), $generatedExtension);

        event(new ConversionHasBeenCompletedEvent($media, $conversion));
    }

    protected function renameInLocalDirectory(
        string $fileNameWithDirectory,
        string $newFileNameWithoutDirectory
    ): string {
        $targetFile = pathinfo($fileNameWithDirectory, PATHINFO_DIRNAME).'/'.$newFileNameWithoutDirectory;

        rename($fileNameWithDirectory, $targetFile);

        return $targetFile;
    }
}
