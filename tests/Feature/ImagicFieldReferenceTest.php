<?php

namespace Ayvazyan10\Imagic\Tests\Feature;

use Ayvazyan10\Imagic\Imagic;
use Ayvazyan10\Imagic\Models\MediaAsset;
use Ayvazyan10\Imagic\Services\ImageTransformer;
use Ayvazyan10\Imagic\Services\MediaStorage;
use Ayvazyan10\Imagic\Tests\TestCase;
use Ayvazyan10\Imagic\Tests\TestUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Fluent;
use Illuminate\Validation\ValidationException;
use Laravel\Nova\Http\Requests\NovaRequest;
use RuntimeException;

class ImagicFieldReferenceTest extends TestCase
{
    public function test_single_and_multiple_references_round_trip_as_enriched_display_values(): void
    {
        $user = TestUser::query()->create(['name' => 'Owner']);
        $first = $this->assetFor($user, 'first.png');
        $second = $this->assetFor($user, 'second.png');
        $request = $this->novaRequest($user);
        $this->app->instance(NovaRequest::class, $request);

        $single = Imagic::make('Image', 'image');
        $single->resolve(new Fluent(['image' => 'media:'.$first->uuid]));
        $singleValue = $single->jsonSerialize()['value'];
        self::assertSame('media:'.$first->uuid, $singleValue['reference']);
        self::assertStringContainsString('/media/'.$first->uuid.'/content', $singleValue['url']);

        $multiple = Imagic::make('Images', 'images')->multiple();
        $multiple->resolve(new Fluent(['images' => json_encode(['media:'.$first->uuid, 'media:'.$second->uuid])]));
        $values = json_decode($multiple->jsonSerialize()['value'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['media:'.$first->uuid, 'media:'.$second->uuid], array_column($values, 'reference'));
    }

    public function test_fill_preserves_mixed_visual_order_and_rejects_foreign_references(): void
    {
        $owner = TestUser::query()->create(['name' => 'Owner']);
        $other = TestUser::query()->create(['name' => 'Other']);
        $first = $this->assetFor($owner, 'first.png');
        $second = $this->assetFor($owner, 'second.png');
        $foreign = $this->assetFor($other, 'foreign.png');
        $field = new ExposedImagic('Images', 'images');
        $field->multiple();
        $model = new Fluent();
        $request = $this->novaRequest($owner, [
            'images_existing' => json_encode(['media:'.$first->uuid, 'media:'.$second->uuid]),
            'images_order' => json_encode(['existing', 'existing']),
        ]);
        $field->fillExposed($request, 'images', $model, 'images');
        self::assertSame(['media:'.$first->uuid, 'media:'.$second->uuid], json_decode($model->images, true));

        $this->expectException(ValidationException::class);
        $field->fillExposed($this->novaRequest($owner, [
            'images_existing' => json_encode(['media:'.$foreign->uuid]),
        ]), 'images', new Fluent(), 'images');
    }

    public function test_foreign_persisted_reference_is_omitted_during_serialization(): void
    {
        $owner = TestUser::query()->create(['name' => 'Owner']);
        $other = TestUser::query()->create(['name' => 'Other']);
        $foreign = $this->assetFor($other, 'foreign.png');
        $request = $this->novaRequest($owner);
        $this->app->instance(NovaRequest::class, $request);
        $field = Imagic::make('Images', 'images')->multiple();
        $field->resolve(new Fluent(['images' => json_encode(['media:'.$foreign->uuid])]));

        self::assertSame([], json_decode($field->jsonSerialize()['value'], true));
    }

    public function test_direct_field_upload_creates_owned_catalog_asset_and_persists_reference(): void
    {
        Storage::fake('imagic-test');
        $owner = TestUser::query()->create(['name' => 'Owner']);
        $request = $this->novaRequest($owner, ['image_existing' => '[]']);
        $request->files->set('image', UploadedFile::fake()->image('avatar.png', 40, 30));
        $model = new Fluent();
        $field = new ExposedImagic('Image', 'image');
        $field->fillExposed($request, 'image', $model, 'image');

        self::assertMatchesRegularExpression('/^media:[0-9a-f-]{36}$/', $model->image);
        $asset = MediaAsset::query()->firstOrFail();
        self::assertSame('media:'.$asset->uuid, $model->image);
        self::assertSame((string) $owner->getAuthIdentifier(), (string) $asset->owner_id);
        Storage::disk('imagic-test')->assertExists($asset->path);
        Storage::disk('imagic-test')->assertExists($asset->thumbnail_path);
    }

    public function test_forged_single_file_array_is_rejected_before_any_asset_is_written(): void
    {
        Storage::fake('imagic-test');
        $owner = TestUser::query()->create(['name' => 'Owner']);
        $request = $this->novaRequest($owner, ['image_existing' => '[]']);
        $request->files->set('image', [
            UploadedFile::fake()->image('one.png', 10, 10),
            UploadedFile::fake()->image('two.png', 10, 10),
        ]);

        try {
            (new ExposedImagic('Image', 'image'))->fillExposed($request, 'image', new Fluent(), 'image');
            self::fail('Expected validation to reject multiple files.');
        } catch (ValidationException) {
            self::assertSame(0, MediaAsset::query()->count());
        }
    }

    public function test_multiple_field_assigns_an_array_to_array_cast_attributes(): void
    {
        $owner = TestUser::query()->create(['name' => 'Owner']);
        $asset = $this->assetFor($owner, 'casted.png');
        $request = $this->novaRequest($owner, [
            'images_existing' => json_encode(['media:'.$asset->uuid]),
        ]);
        $model = new CastedMediaModel();
        $field = new ExposedImagic('Images', 'images');
        $field->multiple()->fillExposed($request, 'images', $model, 'images');

        self::assertSame(['media:'.$asset->uuid], $model->images);
        self::assertSame(json_encode(['media:'.$asset->uuid]), $model->getAttributes()['images']);
    }

    public function test_multiple_upload_rolls_back_earlier_assets_when_a_later_store_fails(): void
    {
        Storage::fake('imagic-test');
        $owner = TestUser::query()->create(['name' => 'Owner']);
        $request = $this->novaRequest($owner, ['images_existing' => '[]']);
        $request->files->set('images', [
            UploadedFile::fake()->image('one.png', 10, 10),
            UploadedFile::fake()->image('two.png', 10, 10),
        ]);
        $this->app->instance(MediaStorage::class, new FailSecondMediaStorage(app(ImageTransformer::class)));
        $field = new ExposedImagic('Images', 'images');
        $field->multiple();

        try {
            $field->fillExposed($request, 'images', new Fluent(), 'images');
            self::fail('Expected the second store to fail.');
        } catch (RuntimeException) {
            self::assertSame(0, MediaAsset::query()->count());
            self::assertSame([], Storage::disk('imagic-test')->allFiles());
        }
    }

    private function novaRequest(TestUser $user, array $data = []): NovaRequest
    {
        $request = NovaRequest::create('/nova-api/example', 'POST', $data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function assetFor(TestUser $owner, string $name): MediaAsset
    {
        $path = 'imagic/originals/'.uniqid().'.png';
        $asset = new MediaAsset([
            'disk' => 'imagic-test', 'path' => $path, 'path_hash' => hash('sha256', $path),
            'name' => $name, 'original_name' => $name, 'mime_type' => 'image/png',
            'extension' => 'png', 'size' => 10, 'width' => 10, 'height' => 10,
            'visibility' => 'private',
        ]);
        $asset->owner_type = $owner->getMorphClass();
        $asset->owner_id = (string) $owner->getAuthIdentifier();
        $asset->save();

        return $asset;
    }
}

class ExposedImagic extends Imagic
{
    public function fillExposed(NovaRequest $request, string $requestAttribute, object $model, string $attribute): void
    {
        $this->fillAttribute($request, $requestAttribute, $model, $attribute);
    }
}

class CastedMediaModel extends Model
{
    public $timestamps = false;

    protected $casts = ['images' => 'array'];

    protected $guarded = [];
}

class FailSecondMediaStorage extends MediaStorage
{
    private int $stores = 0;

    public function store(UploadedFile $file, Authenticatable $owner, ?int $folderId = null, array $options = []): MediaAsset
    {
        if (++$this->stores === 2) {
            throw new RuntimeException('Simulated second write failure.');
        }

        return parent::store($file, $owner, $folderId, $options);
    }
}
