<?php

namespace Ayvazyan10\Imagic\Support;

final class TransformedImage
{
    public function __construct(
        public readonly string $contents,
        public readonly string $mimeType,
        public readonly string $extension,
        public readonly int $width,
        public readonly int $height,
    ) {
    }
}
