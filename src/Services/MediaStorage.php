<?php

namespace Ayvazyan10\Imagic\Services;

use Ayvazyan10\Imagic\Models\MediaAsset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MediaStorage
{
    public function __construct(private readonly ImageTransformer $transformer)
    {
    }

    public function store(UploadedFile $file, Authenticatable $owner, ?int $folderId = null, array $options = []): MediaAsset
    {
        $disk = (string) ($options['disk'] ?? config('imagic.disk', config('nova.storage_disk', 'public')));
        $visibility = (string) ($options['visibility'] ?? config('imagic.visibility', 'private'));
        $transformOptions = (array) ($options['transform'] ?? []);
        $transformed = $this->transformer->transform($file->getRealPath(), $transformOptions);
        $thumbnail = $this->transformer->transform($transformed->contents, [
            'format' => $transformed->extension,
            'quality' => (int) config('imagic.thumbnail.quality', 80),
            'fit_width' => (int) config('imagic.thumbnail.width', 480),
            'fit_height' => (int) config('imagic.thumbnail.height', 320),
            'orientate' => false,
        ]);
        $stem = (string) Str::uuid();
        $base = trim((string) ($options['directory'] ?? config('imagic.directory', 'imagic')), '/');
        $datePath = now()->format('Y/m/d');
        $path = "{$base}/originals/{$datePath}/{$stem}.{$transformed->extension}";
        $thumbnailPath = "{$base}/thumbnails/{$datePath}/{$stem}.{$thumbnail->extension}";
        $filesystem = Storage::disk($disk);
        $written = [];

        try {
            if (! $filesystem->put($path, $transformed->contents, ['visibility' => $visibility])) {
                throw new RuntimeException('Unable to store the uploaded image.');
            }
            $written[] = $path;

            if (! $filesystem->put($thumbnailPath, $thumbnail->contents, ['visibility' => $visibility])) {
                throw new RuntimeException('Unable to store the image thumbnail.');
            }
            $written[] = $thumbnailPath;

            /** @var class-string<MediaAsset> $modelClass */
            $modelClass = config('imagic.media_library.model', MediaAsset::class);
            /** @var MediaAsset $asset */
            $asset = new $modelClass([
                'folder_id' => $folderId,
                'disk' => $disk,
                'path' => $path,
                'path_hash' => hash('sha256', $path),
                'thumbnail_path' => $thumbnailPath,
                'name' => $this->safeDisplayName($file->getClientOriginalName()),
                'original_name' => $this->safeDisplayName($file->getClientOriginalName()),
                'mime_type' => $transformed->mimeType,
                'extension' => $transformed->extension,
                'size' => strlen($transformed->contents),
                'width' => $transformed->width,
                'height' => $transformed->height,
                'checksum' => hash('sha256', $transformed->contents),
                'visibility' => $visibility,
                'meta' => ['source_mime_type' => $file->getMimeType()],
            ]);
            $asset->owner_type = $owner->getMorphClass();
            $asset->owner_id = (string) $owner->getAuthIdentifier();
            $asset->save();

            return $asset;
        } catch (Throwable $exception) {
            if ($written !== []) {
                $filesystem->delete($written);
            }

            throw $exception;
        }
    }

    /**
     * Delete records first so a storage outage cannot leave a usable database
     * reference to content the user requested to remove. Storage failures are
     * logged for operational cleanup and never restore another user's record.
     *
     * @param  iterable<MediaAsset>  $assets
     */
    public function delete(iterable $assets): void
    {
        $pathsByDisk = [];

        DB::transaction(function () use ($assets, &$pathsByDisk): void {
            foreach ($assets as $asset) {
                $pathsByDisk[$asset->disk][] = $asset->path;

                if ($asset->thumbnail_path) {
                    $pathsByDisk[$asset->disk][] = $asset->thumbnail_path;
                }

                $asset->delete();
            }
        });

        foreach ($pathsByDisk as $disk => $paths) {
            try {
                Storage::disk($disk)->delete(array_values(array_unique($paths)));
            } catch (Throwable $exception) {
                Log::warning('Imagic could not delete media objects after deleting their records.', [
                    'disk' => $disk,
                    'paths' => $paths,
                    'exception' => $exception,
                ]);
            }
        }
    }

    public function readStream(MediaAsset $asset, bool $thumbnail = false)
    {
        $path = $thumbnail && $asset->thumbnail_path ? $asset->thumbnail_path : $asset->path;
        $stream = Storage::disk($asset->disk)->readStream($path);

        if ($stream === false) {
            throw new RuntimeException('Unable to read the media object.');
        }

        return $stream;
    }

    private function safeDisplayName(string $name): string
    {
        $name = trim((string) preg_replace('/[\/\\\\\x00-\x1F\x7F]+/u', '-', basename($name)));

        return Str::limit($name !== '' ? $name : 'image', 255, '');
    }
}
