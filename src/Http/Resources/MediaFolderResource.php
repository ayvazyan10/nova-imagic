<?php

namespace Ayvazyan10\Imagic\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MediaFolderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'parent_id' => $this->parent?->uuid,
            'path' => $this->additional['path'] ?? $this->name,
            'created_at' => optional($this->created_at)->toISOString(),
        ];
    }
}
