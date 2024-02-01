<?php

use Spatie\Image\Image;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Tests\TestSupport\TestFileNamer;
use Spatie\MediaLibrary\Tests\TestSupport\TestModels\TestModelWithoutMediaConversions;

beforeEach(function () {
    config()->set('media-library.file_namer', TestFileNamer::class);

    $this->fileName = 'prefix_test_suffix';
});


it('must not be able to be used for exploits using filename', function (string $driver) {
    config()->set('media-library.image_driver', $driver);
    config()->set('media-library.responsive_images.use_tiny_placeholders', false);
    config()->set('media-library.convert_gif_to_webp_using_gif2webp', true);

    $testModel = new class() extends TestModelWithoutMediaConversions
    {
        public function registerMediaCollections(): void
        {
            $this->addMediaCollection('images')
                ->registerMediaConversions(function (Media $media) {
                    $this
                        ->addMediaConversion('webp')
                        ->withResponsiveImages()->withCalculator()
                        ->format('webp');
                });
        }
    };

    $model = $testModel::create(['name' => 'testmodel']);
    $model->addMedia($this->getTestGif())
        ->setFileName("filename.gif -o filename2.gif && mkdir exploit")
        ->toMediaCollection('images');


})->with(['imagick']);
