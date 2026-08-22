<?php

namespace Ayvazyan10\Imagic\Tests\Feature;

use Ayvazyan10\Imagic\Models\MediaAsset;
use Ayvazyan10\Imagic\Tests\TestCase;
use Ayvazyan10\Imagic\Tests\TestUser;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Http\Middleware\Authenticate;
use Laravel\Nova\Http\Middleware\Authorize;

class MediaLibraryApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('imagic-test');
        $this->withoutMiddleware([Authenticate::class, Authorize::class]);
    }

    public function test_index_is_owner_scoped_and_does_not_expose_storage_topology(): void
    {
        $owner = TestUser::query()->create(['name' => 'Owner']);
        $other = TestUser::query()->create(['name' => 'Other']);
        $mine = $this->assetFor($owner, 'mine.png');
        $this->assetFor($other, 'secret.png');

        $response = $this->actingAs($owner)->getJson('/nova-vendor/imagic-test/media');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->uuid)
            ->assertJsonPath('data.0.reference', 'media:'.$mine->uuid)
            ->assertJsonMissing(['name' => 'secret.png']);

        self::assertArrayNotHasKey('disk', $response->json('data.0'));
        self::assertArrayNotHasKey('path', $response->json('data.0'));
    }

    public function test_content_lookup_returns_not_found_for_another_owner(): void
    {
        $owner = TestUser::query()->create(['name' => 'Owner']);
        $other = TestUser::query()->create(['name' => 'Other']);
        $foreign = $this->assetFor($other, 'foreign.png');

        $this->actingAs($owner)
            ->get('/nova-vendor/imagic-test/media/'.$foreign->uuid.'/content')
            ->assertNotFound();
    }

    public function test_custom_api_and_nova_page_routes_are_registered(): void
    {
        self::assertSame('nova-vendor/imagic-test/media', Route::getRoutes()->getByName('imagic.media.index')->uri());
        self::assertSame('nova/imagic-media', Route::getRoutes()->getByName('imagic.media-library')->uri());
    }

    public function test_upload_uses_storage_facade_and_delete_removes_both_objects(): void
    {
        $owner = TestUser::query()->create(['name' => 'Owner']);
        $response = $this->actingAs($owner)->post('/nova-vendor/imagic-test/media', [
            'files' => [\Illuminate\Http\UploadedFile::fake()->image('cloud-safe.png', 40, 30)],
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('data.0.name', 'cloud-safe.png');
        $asset = MediaAsset::query()->firstOrFail();
        Storage::disk('imagic-test')->assertExists($asset->path);
        Storage::disk('imagic-test')->assertExists($asset->thumbnail_path);

        $this->actingAs($owner)
            ->deleteJson('/nova-vendor/imagic-test/media/'.$asset->uuid)
            ->assertNoContent();
        self::assertSame(0, MediaAsset::query()->count());
        Storage::disk('imagic-test')->assertMissing($asset->path);
        Storage::disk('imagic-test')->assertMissing($asset->thumbnail_path);
    }

    public function test_bulk_delete_rejects_a_foreign_uuid_without_partial_deletion(): void
    {
        $owner = TestUser::query()->create(['name' => 'Owner']);
        $other = TestUser::query()->create(['name' => 'Other']);
        $mine = $this->assetFor($owner, 'mine.png');
        $foreign = $this->assetFor($other, 'foreign.png');

        $this->actingAs($owner)->postJson('/nova-vendor/imagic-test/media/bulk-delete', [
            'ids' => [$mine->uuid, $foreign->uuid],
        ])->assertNotFound();

        self::assertSame(2, MediaAsset::query()->count());
        Storage::disk('imagic-test')->assertExists($mine->path);
        Storage::disk('imagic-test')->assertExists($foreign->path);
    }

    public function test_folder_cycle_and_non_empty_deletion_are_rejected(): void
    {
        $owner = TestUser::query()->create(['name' => 'Owner']);
        $root = $this->actingAs($owner)->postJson('/nova-vendor/imagic-test/folders', ['name' => 'Root'])
            ->assertCreated()->json('data.id');
        $child = $this->actingAs($owner)->postJson('/nova-vendor/imagic-test/folders', [
            'name' => 'Child', 'parent_id' => $root,
        ])->assertCreated()->json('data.id');

        $this->actingAs($owner)->patchJson('/nova-vendor/imagic-test/folders/'.$root, [
            'parent_id' => $child,
        ])->assertStatus(422);
        $this->actingAs($owner)->deleteJson('/nova-vendor/imagic-test/folders/'.$root)
            ->assertStatus(409)->assertJsonPath('code', 'folder_not_empty');
    }

    public function test_upload_validation_rejects_non_images_and_configured_size_limit(): void
    {
        $owner = TestUser::query()->create(['name' => 'Owner']);
        config(['imagic.uploads.max_file_size' => 1]);

        $this->actingAs($owner)->post('/nova-vendor/imagic-test/media', [
            'files' => [\Illuminate\Http\UploadedFile::fake()->create('payload.txt', 2, 'text/plain')],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('files.0');

        self::assertSame(0, MediaAsset::query()->count());
    }

    private function assetFor(TestUser $owner, string $name): MediaAsset
    {
        $path = 'imagic/originals/'.uniqid().'.png';
        Storage::disk('imagic-test')->put($path, 'image');
        $asset = new MediaAsset([
            'disk' => 'imagic-test',
            'path' => $path,
            'path_hash' => hash('sha256', $path),
            'name' => $name,
            'original_name' => $name,
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size' => 5,
            'width' => 10,
            'height' => 10,
            'visibility' => 'private',
        ]);
        $asset->owner_type = $owner->getMorphClass();
        $asset->owner_id = (string) $owner->getAuthIdentifier();
        $asset->save();

        return $asset;
    }
}
