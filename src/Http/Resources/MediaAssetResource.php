<?php

namespace Ayvazyan10\Imagic\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MediaAssetResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->uuid,
            'reference' => 'media:'.$this->uuid,
            'name' => $this->name,
            'original_name' => $this->original_name,
            'url' => route('imagic.media.content', ['media' => $this->uuid]),
            'thumbnail_url' => route('imagic.media.thumbnail', ['media' => $this->uuid]),
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size' => $this->size,
            'human_size' => $this->human_size,
            'width' => $this->width,
            'height' => $this->height,
            'folder_id' => $this->folder?->uuid,
            'visibility' => $this->visibility,
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
