<?php

namespace Ayvazyan10\Imagic\Http\Controllers\Concerns;

use Ayvazyan10\Imagic\Models\MediaAsset;
use Ayvazyan10\Imagic\Models\MediaFolder;
use Illuminate\Contracts\Auth\Authenticatable;

trait ResolvesOwnedMedia
{
    protected function ownedAsset(Authenticatable $owner, string $uuid): MediaAsset
    {
        /** @var class-string<MediaAsset> $model */
        $model = config('imagic.media_library.model', MediaAsset::class);

        return $model::query()->ownedBy($owner)->where('uuid', $uuid)->firstOrFail();
    }

    protected function ownedFolder(Authenticatable $owner, string $uuid): MediaFolder
    {
        /** @var class-string<MediaFolder> $model */
        $model = config('imagic.media_library.folder_model', MediaFolder::class);

        return $model::query()->ownedBy($owner)->where('uuid', $uuid)->firstOrFail();
    }
}
