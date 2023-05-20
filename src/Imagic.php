<?php

namespace Ayvazyan10\Imagic;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Intervention\Image\ImageManagerStatic as Image;

class Imagic extends Field
{
    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'imagic';

    /**
     * Optional custom upload directory.
     *
     * @var string|null
     */
    public string|null $customUploadDirectory = null;

    /**
     * A boolean flag indicating whether the field should allow multiple image
     *
     * @var bool
     */
    public bool $multiple = false;

    /**
     *  A boolean flag indicating whether the field should resize the uploaded images.
     *
     * @var bool
     */
    public bool $resize = false;

    /**
     * Optional cropping dimensions and coordinates.
     *
     * @var int|null
     */
    public int|null $cropWidth = null;
    public int|null $cropHeight = null;
    public int|null $cropLeft = null;
    public int|null $cropTop = null;

    /**
     * Optional add watermark to images.
     *
     * @var bool|int|null
     */
    public bool $watermark = false;
    public string|null $watermarkPath = null;
    public string $watermarkPosition = 'bottom-right';
    public int $watermarkX = 0;
    public int $watermarkY = 0;

    /**
     *  Optional resizing dimensions.
     *
     * @var int|null
     */
    public int|null $resizeWidth = null;
    public int|null $resizeHeight = null;

    /**
     *  Optional fit dimensions.
     *
     * @var int|null
     */
    public int|null $fitWidth = null;
    public int|null $fitHeight = null;

    /**
     * A boolean flag indicating whether the field should convert uploaded images to WebP format.
     *
     * @var bool
     */
    public bool $convert = true;

    /**
     * TThe quality of the resulting image, default is 90.
     *
     * @var int
     */
    public int $quality = 90;

    /**
     * The Intervention Image driver, default: gd, alternative: imagick
     *
     * @var string|int
     */
    public string|int $driver = 'gd';

    /**
     * The Intervention Image instance used for magic image manipulations.
     */
    public $image;
    protected $directory;

    /**
     * Allow the user to set a custom upload directory.
     *
     * @param string $path
     * @return $this
     * @throws InvalidArgumentException
     */
    public function directory(string $path): static
    {
        if (Str::startsWith($path, '/') || Str::endsWith($path, '/')) {
            throw new InvalidArgumentException('Directory structure should not start or end with a slash. Only in the middle.');
        }

        $this->customUploadDirectory = $path;

        return $this;
    }

    /**
     * Enable watermarking and specify the watermark image path.
     *
     * @param string $path
     * @param string $position
     * @param int $x
     * @param int $y
     * @return $this
     */
    public function watermark(string $path, string $position = 'bottom-right', int $x = 0, int $y = 0): static
    {
        $this->watermark = true;
        $this->watermarkPath = $path;
        $this->watermarkPosition = $position;
        $this->watermarkX = $x;
        $this->watermarkY = $y;

        return $this;
    }

    /**
     * Specify the size (width and height) the image should be fit into.
     *
     * @param int|null $width
     * @param int|null $height
     * @return $this
     */
    public function fit(int $width = null, int $height = null): static
    {
        $this->fitWidth = $width;
        $this->fitHeight = $height;

        return $this;
    }

    /**
     * Set whether the field should allow multiple image uploads.
     *
     * @return $this
     */
    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    /**
     * Specify the size (width and height) the image should be croped.
     *
     * @param int|null $width
     * @param int|null $height
     * @param int $left
     * @param int $top
     *
     * @return $this
     */
    public function crop(int $width = null, int $height = null, int $left = 0, int $top = 0): static
    {
        $this->cropWidth = $width;
        $this->cropHeight = $height;
        $this->cropLeft = $left;
        $this->cropTop = $top;

        return $this;
    }

    /**
     * Specify the size (width and height) the image should be resized.
     *
     * @param int|null $width
     * @param int|null $height
     * @return $this
     */
    public function resize(int $width = null, int $height = null): static
    {
        $this->resizeWidth = $width;
        $this->resizeHeight = $height;

        return $this;
    }

    /**
     * Set whether the field should allow multiple image uploads.
     *
     * @return $this
     * @throws Exception
     */
    public function quality(int $quality): static
    {
        if ($quality < 0 || $quality > 100) {
            throw new Exception('The quality must ranges from 0 to 100.');
        }

        $this->quality = $quality;

        return $this;
    }

    /**
     * Set whether the field should allow multiple image uploads.
     *
     * @return $this
     * @throws Exception
     */
    public function driver(string $driver): static
    {
        if (!in_array($driver, ['gd', 'imagick'])) {
            throw new Exception("The driver \"$driver\" is not a valid Intervention driver.");
        }

        $this->driver = $driver;

        return $this;
    }


    /**
     * Set whether the field should convert uploaded images to webp format.
     *
     * @param bool $convert
     * @return $this
     */
    public function convert(bool $convert = true): static
    {
        $this->convert = $convert;

        return $this;
    }

    /**
     * Create a new field.
     *
     * @param string $name
     * @param null $attribute
     */
    public function __construct($name, $attribute = null)
    {
        parent::__construct($name, $attribute);

        $directory = [
            'year' => Carbon::now()->format('Y'),
            'month' => Carbon::now()->format('m'),
            'day' => Carbon::now()->format('d')
        ];

        $this->directory = (object)$directory;
    }

    /**
     * Hydrate the given attribute on the model based on the incoming request.
     *
     * @param NovaRequest $request
     * @param string $requestAttribute
     * @param object $model
     * @param string $attribute
     *
     * @return void
     */
    protected function fillAttribute(NovaRequest $request, $requestAttribute, $model, $attribute): void
    {
        $magic = null;

        if (!empty($request->{$requestAttribute})) {
            $magic = $this->imageMagic($request->file($requestAttribute));
        }

        // Get existing images from the request
        $existingImagesString = $request->input($requestAttribute . '_existing', '');
        $existingImages = !empty($existingImagesString) ? explode(',', $existingImagesString) : [];

        parent::fillAttribute($request, $requestAttribute, $model, $attribute);

        if ($this->multiple) {
            // Merge existing images with the new images
            $magic = !empty($magic) ? array_merge($existingImages, $magic) : $existingImages;
            $model->{$attribute} = json_encode($magic);
        } else {
            if ($magic) {
                $model->{$attribute} = $magic;
            }
        }
    }


    /**
     * Magic Happens
     * @param $requestAttribute
     * @return string|array // Return url(s) data
     */
    protected function imageMagic($requestAttribute): string|array
    {
        $destinationPath = public_path(empty($this->customUploadDirectory) ? 'storage/imagic/' . $this->directory->year . '/' . $this->directory->month . '/' . $this->directory->day . '/' : $this->customUploadDirectory . '/');

        if (!is_dir($destinationPath)) {
            mkdir($destinationPath, 0775, true);
        }

        if (is_array($requestAttribute) && $this->multiple) {
            $toJSON = [];

            foreach ($requestAttribute as $image) {
                $toJSON[] = $this->imageManipulations($image, $destinationPath);
            }

            return $toJSON;
        } else {
            return $this->imageManipulations($requestAttribute, $destinationPath);
        }

    }

    public function imageManipulations($image, $destinationPath): string
    {
        // configure with favored image driver (gd by default)
        Image::configure(['driver' => $this->driver]);

        $this->image = Image::make($image->getRealPath());

        $file_name = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);

        if ($this->convert) {
            $this->image->encode('webp');
            $imageName = time() . "_" . $file_name . '.webp';
        } else {
            $imageName = $image->getClientOriginalName();
        }

        if ($this->fitWidth || $this->fitHeight) {
            $this->fitMagic();
        }

        if ($this->watermark && $this->watermarkPath) {
            $this->watermarkMagic();
        }

        if ($this->cropWidth || $this->cropHeight) {
            $this->cropMagic();
        }

        if ($this->resizeWidth || $this->resizeHeight) {
            $this->resizeMagic();
        }

        if (!empty($this->customUploadDirectory)) {
            $image_url = '/' . $this->customUploadDirectory . '/' . $imageName;
        } else {
            $image_url = '/storage/imagic/' . $this->directory->year . '/' . $this->directory->month . '/' . $this->directory->day . '/' . $imageName;
        }

        $this->image->save($destinationPath . $imageName, $this->quality, $this->convert === false ? null : 'webp');
        $this->image->destroy();

        return $image_url;
    }


    /**
     * Magic with image Crop.
     *
     * @return void
     */
    protected function cropMagic(): void
    {
        $this->image->crop($this->cropWidth, $this->cropHeight, $this->cropLeft, $this->cropTop);
    }

    /**
     * Magic with image fit.
     *
     * @return void
     */
    protected function fitMagic(): void
    {
        $this->image->fit($this->fitWidth, $this->fitHeight);
    }

    /**
     * Magic with watermarking.
     *
     * @return void
     */
    protected function watermarkMagic(): void
    {
        $watermark = Image::make($this->watermarkPath);
        $this->image->insert($watermark, $this->watermarkPosition, $this->watermarkX, $this->watermarkY);
    }

    /**
     * Magic with Resize.
     *
     * @return void
     */
    protected function resizeMagic(): void
    {
        $this->image->resize(empty($this->resizeWidth) && empty($this->resizeHeight) ? 1920
            : $this->resizeWidth, empty($this->resizeWidth) && empty($this->resizeHeight) ? 1080
            : $this->resizeHeight, function ($constraint) {
            $constraint->upsize();
            $constraint->aspectRatio();
        });
    }

    /**
     * Prepare the field element for JSON serialization.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'multiple' => $this->multiple,
        ]);
    }
}
