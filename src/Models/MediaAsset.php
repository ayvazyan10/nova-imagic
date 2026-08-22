<?php

namespace Ayvazyan10\Imagic\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class MediaAsset extends Model
{
    protected $table = 'imagic_media_assets';

    protected $fillable = [
        'folder_id', 'disk', 'path', 'path_hash', 'thumbnail_path', 'name', 'original_name',
        'mime_type', 'extension', 'size', 'width', 'height', 'checksum',
        'visibility', 'meta',
    ];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'meta' => 'array',
    ];

    protected $hidden = ['owner_type', 'owner_id'];

    protected static function booted(): void
    {
        static::saving(function (self $asset): void {
            if (! $asset->uuid) {
                $asset->uuid = (string) Str::uuid();
            }

            if ($asset->path) {
                $asset->path_hash = hash('sha256', $asset->path);
            }
        });
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(config('imagic.media_library.folder_model', MediaFolder::class), 'folder_id');
    }

    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        return $query->where('owner_type', $user->getMorphClass())
            ->where('owner_id', (string) $user->getAuthIdentifier());
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return $this->owner_type === $user->getMorphClass()
            && (string) $this->owner_id === (string) $user->getAuthIdentifier();
    }

    protected function humanSize(): Attribute
    {
        return Attribute::get(function (): string {
            $bytes = max(0, (int) $this->size);
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
            $power = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;

            return round($bytes / (1024 ** $power), $power === 0 ? 0 : 1).' '.$units[$power];
        });
    }
}
