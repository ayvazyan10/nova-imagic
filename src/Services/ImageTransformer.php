<?php

namespace Ayvazyan10\Imagic\Services;

use Ayvazyan10\Imagic\Support\TransformedImage;
use InvalidArgumentException;
use RuntimeException;

class ImageTransformer
{
    public function transform(string $source, array $options = []): TransformedImage
    {
        $configured = static fn (string $key, mixed $default): mixed => app()->bound('config') ? config($key, $default) : $default;
        $options = array_merge([
            'driver' => $configured('imagic.transform.driver', 'gd'),
            'format' => $configured('imagic.transform.format', 'webp'),
            'quality' => $configured('imagic.transform.quality', 88),
            'orientate' => $configured('imagic.uploads.orientate', true),
            'fit_width' => null,
            'fit_height' => null,
            'widen' => null,
            'crop_width' => null,
            'crop_height' => null,
            'crop_left' => null,
            'crop_top' => null,
            'resize_width' => null,
            'resize_height' => null,
            'watermark_path' => null,
            'watermark_position' => 'bottom-right',
            'watermark_x' => 0,
            'watermark_y' => 0,
        ], $options);

        $this->validateOptions($options);

        if (class_exists(\Intervention\Image\ImageManagerStatic::class)) {
            return $this->transformWithV2($source, $options);
        }

        if (class_exists(\Intervention\Image\ImageManager::class)) {
            return $this->transformWithV3($source, $options);
        }

        throw new RuntimeException('Intervention Image 2.7 or 3.x is required.');
    }

    private function transformWithV2(string $source, array $options): TransformedImage
    {
        $manager = \Intervention\Image\ImageManagerStatic::class;
        $manager::configure(['driver' => $options['driver']]);
        $image = $manager::make($source);

        try {
            if ($options['orientate'] && method_exists($image, 'orientate')) {
                $image->orientate();
            }

            if ($options['fit_width'] || $options['fit_height']) {
                $width = (int) ($options['fit_width'] ?: $options['fit_height']);
                $height = (int) ($options['fit_height'] ?: $options['fit_width']);
                $image->fit($width, $height, fn ($constraint) => $constraint->upsize());
            }

            if ($options['widen']) {
                $image->widen((int) $options['widen'], fn ($constraint) => $constraint->upsize());
            }

            if ($options['watermark_path']) {
                $watermark = $manager::make($options['watermark_path']);
                $image->insert(
                    $watermark,
                    $options['watermark_position'],
                    (int) $options['watermark_x'],
                    (int) $options['watermark_y'],
                );
                $watermark->destroy();
            }

            if ($options['crop_width'] || $options['crop_height']) {
                $cropWidth = (int) $options['crop_width'];
                $cropHeight = (int) $options['crop_height'];
                $image->crop(
                    $cropWidth,
                    $cropHeight,
                    $options['crop_left'] === null ? max(0, (int) floor(($image->width() - $cropWidth) / 2)) : (int) $options['crop_left'],
                    $options['crop_top'] === null ? max(0, (int) floor(($image->height() - $cropHeight) / 2)) : (int) $options['crop_top'],
                );
            }

            if ($options['resize_width'] || $options['resize_height']) {
                $image->resize(
                    $options['resize_width'] ? (int) $options['resize_width'] : null,
                    $options['resize_height'] ? (int) $options['resize_height'] : null,
                    function ($constraint): void {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    },
                );
            }

            $image->encode($options['format'], (int) $options['quality']);

            return new TransformedImage(
                (string) $image,
                $this->mimeFor($options['format']),
                $this->extensionFor($options['format']),
                (int) $image->width(),
                (int) $image->height(),
            );
        } finally {
            $image->destroy();
        }
    }

    private function transformWithV3(string $source, array $options): TransformedImage
    {
        $driverClass = $options['driver'] === 'imagick'
            ? \Intervention\Image\Drivers\Imagick\Driver::class
            : \Intervention\Image\Drivers\Gd\Driver::class;
        $managerClass = \Intervention\Image\ImageManager::class;
        $manager = new $managerClass(new $driverClass());
        $image = $manager->read($source);

        if ($options['orientate'] && method_exists($image, 'orient')) {
            $image->orient();
        }

        if ($options['fit_width'] || $options['fit_height']) {
            $image->coverDown(
                (int) ($options['fit_width'] ?: $options['fit_height']),
                (int) ($options['fit_height'] ?: $options['fit_width']),
            );
        }

        if ($options['widen']) {
            $image->scaleDown(width: (int) $options['widen']);
        }

        if ($options['watermark_path']) {
            $image->place(
                $options['watermark_path'],
                $options['watermark_position'],
                (int) $options['watermark_x'],
                (int) $options['watermark_y'],
            );
        }

        if ($options['crop_width'] || $options['crop_height']) {
            $cropWidth = (int) $options['crop_width'];
            $cropHeight = (int) $options['crop_height'];
            $image->crop(
                $cropWidth,
                $cropHeight,
                $options['crop_left'] === null ? max(0, (int) floor(($image->width() - $cropWidth) / 2)) : (int) $options['crop_left'],
                $options['crop_top'] === null ? max(0, (int) floor(($image->height() - $cropHeight) / 2)) : (int) $options['crop_top'],
            );
        }

        if ($options['resize_width'] || $options['resize_height']) {
            $image->scaleDown(
                $options['resize_width'] ? (int) $options['resize_width'] : null,
                $options['resize_height'] ? (int) $options['resize_height'] : null,
            );
        }

        $method = match ($this->extensionFor($options['format'])) {
            'jpg' => 'toJpeg',
            'png' => 'toPng',
            'gif' => 'toGif',
            default => 'toWebp',
        };
        $encoded = in_array($method, ['toJpeg', 'toWebp'], true)
            ? $image->{$method}(quality: (int) $options['quality'])
            : $image->{$method}();

        return new TransformedImage(
            (string) $encoded,
            $this->mimeFor($options['format']),
            $this->extensionFor($options['format']),
            (int) $image->width(),
            (int) $image->height(),
        );
    }

    private function validateOptions(array $options): void
    {
        if (! in_array($options['driver'], ['gd', 'imagick'], true)) {
            throw new InvalidArgumentException('The image driver must be gd or imagick.');
        }

        if (! in_array(strtolower((string) $options['format']), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            throw new InvalidArgumentException('The output format must be jpg, png, gif, or webp.');
        }

        if ((int) $options['quality'] < 0 || (int) $options['quality'] > 100) {
            throw new InvalidArgumentException('Image quality must be between 0 and 100.');
        }
    }

    private function extensionFor(string $format): string
    {
        return strtolower($format) === 'jpeg' ? 'jpg' : strtolower($format);
    }

    private function mimeFor(string $format): string
    {
        return match ($this->extensionFor($format)) {
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            default => 'image/webp',
        };
    }
}
