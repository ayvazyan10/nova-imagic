<?php

namespace Ayvazyan10\Imagic\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class MediaFolder extends Model
{
    protected $table = 'imagic_media_folders';

    protected $fillable = ['parent_id', 'name'];

    protected static function booted(): void
    {
        static::creating(function (self $folder): void {
            $folder->uuid ??= (string) Str::uuid();
        });
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(config('imagic.media_library.model', MediaAsset::class), 'folder_id');
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
}
