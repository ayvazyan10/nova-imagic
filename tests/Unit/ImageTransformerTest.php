<?php

namespace Ayvazyan10\Imagic\Tests\Unit;

use Ayvazyan10\Imagic\Services\ImageTransformer;
use PHPUnit\Framework\TestCase;

class ImageTransformerTest extends TestCase
{
    public function test_omitted_crop_coordinates_crop_from_the_center(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD is required.');
        }

        $canvas = imagecreatetruecolor(100, 20);
        $red = imagecolorallocate($canvas, 255, 0, 0);
        $green = imagecolorallocate($canvas, 0, 255, 0);
        imagefilledrectangle($canvas, 0, 0, 99, 19, $red);
        imagefilledrectangle($canvas, 40, 0, 59, 19, $green);
        ob_start();
        imagepng($canvas);
        $source = (string) ob_get_clean();
        imagedestroy($canvas);

        $result = (new ImageTransformer())->transform($source, [
            'driver' => 'gd', 'format' => 'png', 'quality' => 90,
            'orientate' => false, 'crop_width' => 20, 'crop_height' => 20,
        ]);
        $cropped = imagecreatefromstring($result->contents);
        $color = imagecolorsforindex($cropped, imagecolorat($cropped, 10, 10));
        imagedestroy($cropped);

        self::assertGreaterThan(200, $color['green']);
        self::assertLessThan(30, $color['red']);
    }
}
