<?php

namespace Ayvazyan10\Imagic\Http\Controllers;

use Ayvazyan10\Imagic\Http\Controllers\Concerns\ResolvesOwnedMedia;
use Ayvazyan10\Imagic\Http\Requests\StoreFolderRequest;
use Ayvazyan10\Imagic\Http\Requests\UpdateFolderRequest;
use Ayvazyan10\Imagic\Models\MediaFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class MediaFolderController extends Controller
{
    use ResolvesOwnedMedia;

    public function store(StoreFolderRequest $request): JsonResponse
    {
        $parent = $request->filled('parent_id')
            ? $this->ownedFolder($request->user(), (string) $request->string('parent_id'))
            : null;
        /** @var class-string<MediaFolder> $model */
        $model = config('imagic.media_library.folder_model', MediaFolder::class);
        /** @var MediaFolder $folder */
        $folder = new $model([
            'name' => trim($request->validated('name')),
            'parent_id' => $parent?->getKey(),
        ]);
        $folder->owner_type = $request->user()->getMorphClass();
        $folder->owner_id = (string) $request->user()->getAuthIdentifier();
        $folder->save();
        $folder->load('parent:id,uuid');

        return response()->json(['data' => $this->serialize($folder)], 201);
    }

    public function update(UpdateFolderRequest $request, string $folder): JsonResponse
    {
        $folderModel = $this->ownedFolder($request->user(), $folder);
        $validated = $request->validated();

        if (array_key_exists('parent_id', $validated)) {
            $parent = $validated['parent_id']
                ? $this->ownedFolder($request->user(), $validated['parent_id'])
                : null;

            abort_if($parent && $this->wouldCreateCycle($folderModel, $parent), 422, 'A folder cannot be moved into itself or one of its descendants.');
            $folderModel->parent_id = $parent?->getKey();
        }

        if (array_key_exists('name', $validated)) {
            $folderModel->name = trim($validated['name']);
        }

        $folderModel->save();
        $folderModel->load('parent:id,uuid');

        return response()->json(['data' => $this->serialize($folderModel)]);
    }

    public function destroy(UpdateFolderRequest $request, string $folder): JsonResponse
    {
        $folderModel = $this->ownedFolder($request->user(), $folder);

        if ($folderModel->children()->exists() || $folderModel->assets()->exists()) {
            return response()->json([
                'message' => 'Only empty folders can be deleted. Move or delete their contents first.',
                'code' => 'folder_not_empty',
            ], 409);
        }

        $folderModel->delete();

        return response()->json(null, 204);
    }

    private function wouldCreateCycle(MediaFolder $folder, MediaFolder $parent): bool
    {
        $seen = [];
        $candidate = $parent;

        while ($candidate && ! isset($seen[$candidate->getKey()])) {
            if ($candidate->is($folder)) {
                return true;
            }

            $seen[$candidate->getKey()] = true;
            $candidate = $candidate->parent()->first();
        }

        return false;
    }

    private function serialize(MediaFolder $folder): array
    {
        return [
            'id' => $folder->uuid,
            'name' => $folder->name,
            'parent_id' => $folder->parent?->uuid,
            'created_at' => optional($folder->created_at)->toISOString(),
        ];
    }
}
