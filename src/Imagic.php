<?php

namespace Ayvazyan10\Imagic;

use Ayvazyan10\Imagic\Models\MediaAsset;
use Ayvazyan10\Imagic\Services\ImageTransformer;
use Ayvazyan10\Imagic\Services\MediaStorage;
use Ayvazyan10\Imagic\Support\StoredFieldUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Throwable;

class Imagic extends Field
{
    public $component = 'imagic';

    public ?string $customUploadDirectory = null;

    public bool $multiple = false;

    public ?int $cropWidth = null;

    public ?int $cropHeight = null;

    public ?int $cropLeft = null;

    public ?int $cropTop = null;

    public ?int $widenResize = null;

    public bool $watermark = false;

    public ?string $watermarkPath = null;

    public string $watermarkPosition = 'bottom-right';

    public int $watermarkX = 0;

    public int $watermarkY = 0;

    public ?int $resizeWidth = null;

    public ?int $resizeHeight = null;

    public ?int $fitWidth = null;

    public ?int $fitHeight = null;

    public bool $convert = true;

    public int $quality = 90;

    public string $driver = 'gd';

    public bool $mediaLibraryEnabled = true;

    public bool $liveCropEnabled = false;

    public ?float $cropAspectRatioValue = null;

    public ?int $maximumFiles = null;

    public ?int $maximumFileSize = null;

    protected ?string $disk = null;

    protected ?string $visibility = null;

    public function __construct($name, $attribute = null)
    {
        parent::__construct($name, $attribute);

        $this->mediaLibraryEnabled = (bool) config('imagic.field.media_library', true);
        $this->liveCropEnabled = (bool) config('imagic.field.live_crop', false);
        $this->cropAspectRatioValue = config('imagic.field.crop_aspect_ratio');
        $this->maximumFiles = (int) config('imagic.field.max_files', config('imagic.uploads.max_files', 20));
        $this->maximumFileSize = (int) config('imagic.uploads.max_file_size', 12288) * 1024;
    }

    public function directory(string $path): static
    {
        $path = trim($path);

        if ($path === '' || Str::startsWith($path, '/') || Str::endsWith($path, '/') || str_contains($path, '\\') || preg_match('#(^|/)\.\.?(?:/|$)#', $path)) {
            throw new InvalidArgumentException('The directory must be a safe relative storage path without leading, trailing, dot, or backslash segments.');
        }

        $this->customUploadDirectory = $path;

        return $this;
    }

    public function watermark(string $path, string $position = 'bottom-right', int $x = 0, int $y = 0): static
    {
        $this->watermark = true;
        $this->watermarkPath = $path;
        $this->watermarkPosition = $position;
        $this->watermarkX = $x;
        $this->watermarkY = $y;

        return $this;
    }

    public function fit(?int $width = null, ?int $height = null): static
    {
        $this->fitWidth = $width;
        $this->fitHeight = $height;

        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    /** Omitted coordinates center the crop; explicit 0,0 means top-left. */
    public function crop(?int $width = null, ?int $height = null, ?int $left = null, ?int $top = null): static
    {
        if (($width === null) !== ($height === null)) {
            throw new InvalidArgumentException('Crop width and height must be provided together.');
        }

        $this->cropWidth = $width;
        $this->cropHeight = $height;
        $this->cropLeft = $left;
        $this->cropTop = $top;

        return $this;
    }

    public function resize(?int $width = null, ?int $height = null): static
    {
        $this->resizeWidth = $width;
        $this->resizeHeight = $height;

        return $this;
    }

    public function widen(?int $width = null): static
    {
        $this->widenResize = $width;

        return $this;
    }

    public function quality(int $quality): static
    {
        if ($quality < 0 || $quality > 100) {
            throw new InvalidArgumentException('The quality must be between 0 and 100.');
        }

        $this->quality = $quality;

        return $this;
    }

    public function driver(string $driver): static
    {
        if (! in_array($driver, ['gd', 'imagick'], true)) {
            throw new InvalidArgumentException("The driver \"{$driver}\" must be gd or imagick.");
        }

        $this->driver = $driver;

        return $this;
    }

    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function visibility(string $visibility): static
    {
        if (! in_array($visibility, ['public', 'private'], true)) {
            throw new InvalidArgumentException('Visibility must be public or private.');
        }

        $this->visibility = $visibility;

        return $this;
    }

    public function convert(bool $convert = true): static
    {
        $this->convert = $convert;

        return $this;
    }

    public function mediaLibrary(bool $enabled = true): static
    {
        $this->mediaLibraryEnabled = $enabled;

        return $this;
    }

    public function liveCrop(bool $enabled = true, ?float $aspectRatio = null): static
    {
        if ($aspectRatio !== null && $aspectRatio <= 0) {
            throw new InvalidArgumentException('The crop aspect ratio must be greater than zero.');
        }

        $this->liveCropEnabled = $enabled;
        $this->cropAspectRatioValue = $aspectRatio;

        return $this;
    }

    public function cropAspectRatio(?float $aspectRatio): static
    {
        if ($aspectRatio !== null && $aspectRatio <= 0) {
            throw new InvalidArgumentException('The crop aspect ratio must be greater than zero.');
        }

        $this->cropAspectRatioValue = $aspectRatio;

        return $this;
    }

    public function maxFiles(int $maximum): static
    {
        if ($maximum < 1) {
            throw new InvalidArgumentException('The maximum file count must be at least one.');
        }

        $this->maximumFiles = $maximum;

        return $this;
    }

    /** Configure the client and server limit in bytes. */
    public function maxFileSize(int $bytes): static
    {
        if ($bytes < 1) {
            throw new InvalidArgumentException('The maximum file size must be at least one byte.');
        }

        $this->maximumFileSize = $bytes;

        return $this;
    }

    protected function fillAttribute(NovaRequest $request, $requestAttribute, $model, $attribute): void
    {
        $hasExistingInput = $request->exists($requestAttribute.'_existing');
        $existing = $this->parseList($request->input($requestAttribute.'_existing'));
        $this->assertOwnedReferences($request, $existing, $requestAttribute);
        $files = $this->uploadedFiles($request->file($requestAttribute));

        if ((! $this->multiple && count($files) > 1)
            || ($this->multiple && (count($existing) + count($files)) > (int) $this->maximumFiles)) {
            throw ValidationException::withMessages([
                $requestAttribute => 'The selected images exceed the field maximum of '.($this->multiple ? $this->maximumFiles : 1).'.',
            ]);
        }

        foreach ($files as $file) {
            $this->validateUpload($file, $requestAttribute);
        }

        $storedUploads = [];

        try {
            foreach ($files as $file) {
                $storedUploads[] = $this->storeUpload($request, $file);
            }
        } catch (Throwable $exception) {
            $this->rollbackUploads($storedUploads);

            throw $exception;
        }

        $uploads = array_map(fn (StoredFieldUpload $upload): string => $upload->value, $storedUploads);

        if ($this->multiple) {
            $ordered = $this->applyOrder($existing, $uploads, $this->parseList($request->input($requestAttribute.'_order')));
            $values = array_values($ordered);
            $model->{$attribute} = method_exists($model, 'hasCast') && $model->hasCast($attribute, ['array', 'json', 'object', 'collection'])
                ? $values
                : json_encode($values, JSON_UNESCAPED_SLASHES);

            return;
        }

        if ($uploads !== []) {
            $model->{$attribute} = $uploads[0];
        } elseif ($hasExistingInput) {
            $model->{$attribute} = $existing[0] ?? null;
        }
    }

    public function resolve($resource, $attribute = null): void
    {
        parent::resolve($resource, $attribute);

        $values = $this->parseList($this->value);
        $references = collect($values)
            ->filter(fn ($value): bool => is_string($value) && preg_match('/^media:([0-9a-f-]{36})$/i', $value) === 1);

        if ($references->isEmpty()) {
            return;
        }

        try {
            $request = app(NovaRequest::class);
            $user = $request->user();

            if (! $user) {
                return;
            }

            /** @var class-string<MediaAsset> $model */
            $model = config('imagic.media_library.model', MediaAsset::class);
            $uuids = $references->map(fn (string $reference): string => substr($reference, 6))->all();
            $assets = $model::query()->ownedBy($user)->whereIn('uuid', $uuids)->get()->keyBy('uuid');
            $resolved = array_map(function ($value) use ($assets) {
                if (! is_string($value) || ! str_starts_with($value, 'media:')) {
                    return $value;
                }

                $asset = $assets->get(substr($value, 6));

                return $asset ? $this->serializeReference($asset) : null;
            }, $values);
            $resolved = array_values(array_filter($resolved, fn ($value): bool => $value !== null));

            $this->value = $this->multiple
                ? json_encode($resolved, JSON_UNESCAPED_SLASHES)
                : ($resolved[0] ?? null);
        } catch (Throwable) {
            // Console and non-Nova serialization retain the persisted token.
        }
    }

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'multiple' => $this->multiple,
            'mediaLibrary' => $this->mediaLibraryEnabled && (bool) config('imagic.media_library.enabled', true),
            'mediaApiBase' => '/'.trim((string) config('imagic.media_library.api_path', 'nova-vendor/imagic'), '/'),
            'liveCrop' => $this->liveCropEnabled,
            'cropAspectRatio' => $this->cropAspectRatioValue,
            'cropWidth' => $this->cropWidth,
            'cropHeight' => $this->cropHeight,
            'maxFiles' => $this->multiple ? $this->maximumFiles : 1,
            'maxFileSize' => $this->maximumFileSize,
            'acceptedTypes' => implode(',', (array) config('imagic.uploads.allowed_mime_types', [])),
        ]);
    }

    private function storeUpload(NovaRequest $request, UploadedFile $file): StoredFieldUpload
    {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        $format = $this->convert ? 'webp' : ($extension === 'jpeg' ? 'jpg' : $extension);
        $options = [
            'disk' => $this->disk ?: config('imagic.disk', config('nova.storage_disk', 'public')),
            'directory' => $this->customUploadDirectory ?: config('imagic.directory', 'imagic'),
            'visibility' => $this->visibility ?: config('imagic.field.visibility', config('imagic.visibility', 'private')),
            'transform' => [
                'driver' => $this->driver,
                'format' => $format,
                'quality' => $this->quality,
                'fit_width' => $this->fitWidth,
                'fit_height' => $this->fitHeight,
                'widen' => $this->widenResize,
                'crop_width' => $this->cropWidth,
                'crop_height' => $this->cropHeight,
                'crop_left' => $this->cropLeft,
                'crop_top' => $this->cropTop,
                'resize_width' => $this->resizeWidth,
                'resize_height' => $this->resizeHeight,
                'watermark_path' => $this->watermark ? $this->watermarkPath : null,
                'watermark_position' => $this->watermarkPosition,
                'watermark_x' => $this->watermarkX,
                'watermark_y' => $this->watermarkY,
            ],
        ];

        if ($this->mediaLibraryEnabled && (bool) config('imagic.media_library.enabled', true)) {
            if (! $request->user()) {
                throw ValidationException::withMessages([$this->attribute => 'An authenticated Nova user is required to store media.']);
            }

            $asset = app(MediaStorage::class)->store($file, $request->user(), null, $options);

            return new StoredFieldUpload('media:'.$asset->uuid, $asset);
        }

        $image = app(ImageTransformer::class)->transform($file->getRealPath(), $options['transform']);
        $disk = Storage::disk($options['disk']);
        $path = trim($options['directory'], '/').'/'.now()->format('Y/m/d').'/'.Str::uuid().'.'.$image->extension;

        if (! $disk->put($path, $image->contents, ['visibility' => $options['visibility']])) {
            throw ValidationException::withMessages([$this->attribute => 'The image could not be stored.']);
        }

        return new StoredFieldUpload($disk->url($path), null, $options['disk'], $path);
    }

    /** @param  array<StoredFieldUpload>  $uploads */
    private function rollbackUploads(array $uploads): void
    {
        $assets = collect($uploads)->pluck('asset')->filter();

        if ($assets->isNotEmpty()) {
            app(MediaStorage::class)->delete($assets);
        }

        collect($uploads)
            ->filter(fn (StoredFieldUpload $upload): bool => $upload->asset === null && $upload->disk && $upload->path)
            ->groupBy('disk')
            ->each(function ($items, string $disk): void {
                Storage::disk($disk)->delete($items->pluck('path')->all());
            });
    }

    private function validateUpload(UploadedFile $file, string $attribute): void
    {
        $maxKilobytes = max(1, (int) ceil(($this->maximumFileSize ?? 1) / 1024));
        $extensions = implode(',', (array) config('imagic.uploads.allowed_extensions', []));
        $mimes = implode(',', (array) config('imagic.uploads.allowed_mime_types', []));
        $maxWidth = (int) config('imagic.uploads.max_width', 12000);
        $maxHeight = (int) config('imagic.uploads.max_height', 12000);
        $validator = Validator::make(['file' => $file], [
            'file' => [
                'required', 'file', 'image', "max:{$maxKilobytes}",
                "mimes:{$extensions}", "mimetypes:{$mimes}",
                "dimensions:max_width={$maxWidth},max_height={$maxHeight}",
            ],
        ]);
        $validator->after(function ($validator) use ($file): void {
            $size = @getimagesize($file->getRealPath());
            $maxPixels = (int) config('imagic.uploads.max_pixels', 40_000_000);

            if ($size === false || ((int) $size[0] * (int) $size[1]) > $maxPixels) {
                $validator->errors()->add('file', 'The image dimensions are invalid or exceed the configured pixel limit.');
            }
        });

        try {
            $validator->validate();
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([$attribute => $exception->validator->errors()->first('file')]);
        }
    }

    private function assertOwnedReferences(NovaRequest $request, array $values, string $attribute): void
    {
        $malformed = collect($values)->contains(
            fn ($value): bool => is_string($value)
                && str_starts_with($value, 'media:')
                && preg_match('/^media:[0-9a-f-]{36}$/i', $value) !== 1,
        );

        if ($malformed) {
            throw ValidationException::withMessages([$attribute => 'One or more media references are invalid.']);
        }

        $uuids = collect($values)
            ->filter(fn ($value): bool => is_string($value) && str_starts_with($value, 'media:'))
            ->map(fn (string $value): ?string => preg_match('/^media:([0-9a-f-]{36})$/i', $value, $matches) ? $matches[1] : null)
            ->filter()
            ->unique()
            ->values();

        if ($uuids->isEmpty()) {
            return;
        }

        if (! $request->user()) {
            throw ValidationException::withMessages([$attribute => 'An authenticated Nova user is required to select media.']);
        }

        /** @var class-string<MediaAsset> $model */
        $model = config('imagic.media_library.model', MediaAsset::class);
        $ownedCount = $model::query()->ownedBy($request->user())->whereIn('uuid', $uuids)->count();

        if ($ownedCount !== $uuids->count()) {
            throw ValidationException::withMessages([$attribute => 'One or more selected media items are unavailable.']);
        }
    }

    private function uploadedFiles(mixed $files): array
    {
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        return array_values(array_filter((array) $files, fn ($file): bool => $file instanceof UploadedFile));
    }

    private function parseList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return array_values($value);
        }

        if (! is_string($value)) {
            return [$value];
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return is_array($decoded) ? array_values($decoded) : [$decoded];
        }

        return str_contains($value, ',') ? array_values(array_filter(explode(',', $value), 'strlen')) : [$value];
    }

    private function applyOrder(array $existing, array $uploads, array $order): array
    {
        if ($order === []) {
            return array_merge($existing, $uploads);
        }

        $result = [];

        foreach ($order as $token) {
            $token = (string) $token;

            if (($token === 'existing' || str_starts_with($token, 'existing:')) && $existing !== []) {
                $result[] = array_shift($existing);
            } elseif (($token === 'upload' || str_starts_with($token, 'upload:')) && $uploads !== []) {
                $result[] = array_shift($uploads);
            }
        }

        return array_merge($result, $existing, $uploads);
    }

    private function serializeReference(MediaAsset $asset): array
    {
        return [
            'id' => $asset->uuid,
            'reference' => 'media:'.$asset->uuid,
            'path' => 'media:'.$asset->uuid,
            'url' => route('imagic.media.content', ['media' => $asset->uuid]),
            'thumbnail_url' => route('imagic.media.thumbnail', ['media' => $asset->uuid]),
            'name' => $asset->name,
            'mime_type' => $asset->mime_type,
            'extension' => $asset->extension,
            'size' => $asset->size,
            'width' => $asset->width,
            'height' => $asset->height,
        ];
    }
}
