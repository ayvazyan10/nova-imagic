<?php

namespace Ayvazyan10\Imagic\Http\Controllers;

use Ayvazyan10\Imagic\Http\Controllers\Concerns\ResolvesOwnedMedia;
use Ayvazyan10\Imagic\Http\Requests\BulkDeleteMediaRequest;
use Ayvazyan10\Imagic\Http\Requests\MediaIndexRequest;
use Ayvazyan10\Imagic\Http\Requests\StoreMediaRequest;
use Ayvazyan10\Imagic\Http\Requests\UpdateMediaRequest;
use Ayvazyan10\Imagic\Http\Resources\MediaAssetResource;
use Ayvazyan10\Imagic\Models\MediaAsset;
use Ayvazyan10\Imagic\Models\MediaFolder;
use Ayvazyan10\Imagic\Services\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Throwable;

class MediaAssetController extends Controller
{
    use ResolvesOwnedMedia;

    public function __construct(private readonly MediaStorage $storage)
    {
    }

    public function index(MediaIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        /** @var class-string<MediaAsset> $assetModel */
        $assetModel = config('imagic.media_library.model', MediaAsset::class);
        $query = $assetModel::query()->ownedBy($user)->with('folder:id,uuid,name');
        $search = trim((string) ($validated['search'] ?? ''));

        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $query->where(function ($query) use ($escaped): void {
                $query->where('name', 'like', "%{$escaped}%")
                    ->orWhere('original_name', 'like', "%{$escaped}%");
            });
        }

        if (! empty($validated['mime_type'])) {
            $mime = $validated['mime_type'];
            str_ends_with($mime, '/*')
                ? $query->where('mime_type', 'like', substr($mime, 0, -1).'%')
                : $query->where('mime_type', $mime);
        }

        $folderFilter = $validated['folder_id'] ?? $validated['folder'] ?? 'all';
        if ($folderFilter === 'root') {
            $query->whereNull('folder_id');
        } elseif ($folderFilter !== 'all' && $folderFilter !== null && $folderFilter !== '') {
            $query->where('folder_id', $this->ownedFolder($user, $folderFilter)->getKey());
        }

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction)->orderBy('id', $direction);
        $paginator = $query->paginate((int) ($validated['per_page'] ?? config('imagic.media_library.per_page', 24)));
        $folders = $this->foldersFor($user);

        return response()->json([
            'data' => MediaAssetResource::collection(collect($paginator->items()))->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'folders' => $folders,
        ]);
    }

    public function store(StoreMediaRequest $request): JsonResponse
    {
        $user = $request->user();
        $folder = $request->filled('folder_id') ? $this->ownedFolder($user, (string) $request->string('folder_id')) : null;
        $assets = collect();

        try {
            foreach ($request->file('files', []) as $file) {
                $assets->push($this->storage->store($file, $user, $folder?->getKey()));
            }
        } catch (Throwable $exception) {
            if ($assets->isNotEmpty()) {
                $this->storage->delete($assets);
            }

            throw $exception;
        }

        $assets->each->load('folder:id,uuid,name');

        return response()->json([
            'data' => MediaAssetResource::collection($assets)->resolve($request),
        ], 201);
    }

    public function update(UpdateMediaRequest $request, string $media): JsonResponse
    {
        $asset = $this->ownedAsset($request->user(), $media);
        $validated = $request->validated();

        if (array_key_exists('folder_id', $validated)) {
            $asset->folder_id = $validated['folder_id']
                ? $this->ownedFolder($request->user(), $validated['folder_id'])->getKey()
                : null;
        }

        if (array_key_exists('name', $validated)) {
            $asset->name = trim($validated['name']);
        }

        $asset->save();
        $asset->load('folder:id,uuid,name');

        return response()->json(['data' => (new MediaAssetResource($asset))->resolve($request)]);
    }

    public function destroy(UpdateMediaRequest $request, string $media): JsonResponse
    {
        $asset = $this->ownedAsset($request->user(), $media);
        $this->storage->delete([$asset]);

        return response()->json(null, 204);
    }

    public function bulkDestroy(BulkDeleteMediaRequest $request): JsonResponse
    {
        /** @var class-string<MediaAsset> $model */
        $model = config('imagic.media_library.model', MediaAsset::class);
        $ids = $request->validated('ids');
        $assets = $model::query()->ownedBy($request->user())->whereIn('uuid', $ids)->get();

        abort_unless($assets->count() === count($ids), 404);
        $this->storage->delete($assets);

        return response()->json(null, 204);
    }

    private function foldersFor($user): array
    {
        /** @var class-string<MediaFolder> $model */
        $model = config('imagic.media_library.folder_model', MediaFolder::class);
        $folders = $model::query()->ownedBy($user)->with('parent:id,uuid')->orderBy('name')->get();

        return $folders->map(function (MediaFolder $folder) use ($folders): array {
            return [
                'id' => $folder->uuid,
                'name' => $folder->name,
                'parent_id' => $folder->parent?->uuid,
                'path' => $this->folderPath($folder, $folders),
                'created_at' => optional($folder->created_at)->toISOString(),
            ];
        })->sortBy('path', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    private function folderPath(MediaFolder $folder, Collection $folders): string
    {
        $segments = [$folder->name];
        $seen = [$folder->getKey() => true];
        $parentId = $folder->parent_id;

        while ($parentId && ! isset($seen[$parentId])) {
            $seen[$parentId] = true;
            $parent = $folders->firstWhere('id', $parentId);

            if (! $parent) {
                break;
            }

            array_unshift($segments, $parent->name);
            $parentId = $parent->parent_id;
        }

        return implode('/', $segments);
    }
}
