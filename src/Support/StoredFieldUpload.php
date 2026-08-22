<?php

namespace Ayvazyan10\Imagic\Support;

use Ayvazyan10\Imagic\Models\MediaAsset;

final class StoredFieldUpload
{
    public function __construct(
        public readonly string $value,
        public readonly ?MediaAsset $asset = null,
        public readonly ?string $disk = null,
        public readonly ?string $path = null,
    ) {
    }
}
