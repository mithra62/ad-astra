<?php

namespace Tests\Unit\Field\Types;

use App\Field\Types\FileUpload;
use App\Models\Field;
use App\Models\Field\Type;
use App\Models\Media;
use App\Models\Media\Library;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FileUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionProperty(FileUpload::class, 'libraryHandleCache');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function make(array $settings = []): FileUpload
    {
        return new FileUpload($settings);
    }

    private function callProtected(FileUpload $instance, string $method): mixed
    {
        $ref = new \ReflectionMethod(FileUpload::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke($instance);
    }

    private function makeLibrary(string $handle = 'uploads'): Library
    {
        return Library::create([
            'name' => ucfirst($handle).' Library',
            'handle' => $handle,
            'adapter' => 'local',
        ]);
    }

    // -------------------------------------------------------------------------
    // storageColumn / isRelational
    // -------------------------------------------------------------------------

    public function test_storage_column_is_value_json(): void
    {
        $this->assertEquals('value_json', $this->make()->storageColumn());
    }

    public function test_is_relational_returns_false(): void
    {
        $this->assertFalse($this->make()->isRelational());
    }

    // -------------------------------------------------------------------------
    // normaliseIds
    // -------------------------------------------------------------------------

    public function test_normalise_ids_from_json_string(): void
    {
        $result = $this->make()->normaliseIds('[1,2,3]');

        $this->assertEquals([1, 2, 3], $result);
    }

    public function test_normalise_ids_from_array(): void
    {
        $result = $this->make()->normaliseIds(['1', '2', '3']);

        $this->assertEquals([1, 2, 3], $result);
    }

    public function test_normalise_ids_from_collection(): void
    {
        $media = Media::factory()->count(2)->create();

        $result = $this->make()->normaliseIds($media);

        $this->assertEquals($media->pluck('id')->map('intval')->all(), $result);
    }

    public function test_normalise_ids_empty_string_returns_empty_array(): void
    {
        $this->assertEquals([], $this->make()->normaliseIds(''));
    }

    public function test_normalise_ids_null_returns_empty_array(): void
    {
        $this->assertEquals([], $this->make()->normaliseIds(null));
    }

    // -------------------------------------------------------------------------
    // cast
    // -------------------------------------------------------------------------

    public function test_cast_decodes_json_string_to_int_array(): void
    {
        $result = $this->make()->cast('[10, 20, 30]');

        $this->assertSame([10, 20, 30], $result);
    }

    public function test_cast_passes_through_array(): void
    {
        $result = $this->make()->cast(['5', '10']);

        $this->assertSame([5, 10], $result);
    }

    public function test_cast_returns_empty_for_invalid_json(): void
    {
        $this->assertSame([], $this->make()->cast('not json'));
    }

    public function test_cast_returns_empty_for_null(): void
    {
        $this->assertSame([], $this->make()->cast(null));
    }

    // -------------------------------------------------------------------------
    // value()
    // -------------------------------------------------------------------------

    public function test_value_returns_collection_of_media_models(): void
    {
        $media = Media::factory()->count(2)->create();
        $ids = $media->pluck('id')->all();

        $result = $this->make()->value(json_encode($ids));

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
    }

    public function test_value_returns_empty_collection_for_empty_input(): void
    {
        $result = $this->make()->value('[]');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    public function test_value_preserves_saved_sort_order(): void
    {
        $first = Media::factory()->create();
        $second = Media::factory()->create();

        // Store second before first — value() should return in that order.
        $result = $this->make()->value(json_encode([$second->id, $first->id]));

        $this->assertEquals($second->id, $result->first()->id);
        $this->assertEquals($first->id, $result->last()->id);
    }

    // -------------------------------------------------------------------------
    // validate — min / max count
    // -------------------------------------------------------------------------

    public function test_validate_returns_true_for_empty_value_with_no_constraints(): void
    {
        $result = $this->make()->validate('[]');

        $this->assertTrue($result);
    }

    public function test_validate_returns_error_when_below_min(): void
    {
        $type = $this->make(['min' => 2]);
        $media = Media::factory()->create();

        $result = $type->validate(json_encode([$media->id]));

        $this->assertIsString($result);
        $this->assertStringContainsString('2', $result);
    }

    public function test_validate_passes_when_count_meets_min(): void
    {
        $type = $this->make(['min' => 1]);
        $media = Media::factory()->create();

        $result = $type->validate(json_encode([$media->id]));

        $this->assertTrue($result);
    }

    public function test_validate_returns_error_when_above_max(): void
    {
        $type = $this->make(['max' => 1]);
        $media = Media::factory()->count(2)->create();

        $result = $type->validate(json_encode($media->pluck('id')->all()));

        $this->assertIsString($result);
    }

    public function test_validate_passes_when_count_meets_max(): void
    {
        $type = $this->make(['max' => 2]);
        $media = Media::factory()->count(2)->create();

        $result = $type->validate(json_encode($media->pluck('id')->all()));

        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // validate — ID existence
    // -------------------------------------------------------------------------

    public function test_validate_returns_error_when_id_does_not_exist(): void
    {
        $result = $this->make()->validate('[99999]');

        $this->assertIsString($result);
        $this->assertStringContainsString('no longer exist', $result);
    }

    public function test_validate_passes_when_all_ids_exist(): void
    {
        $media = Media::factory()->count(2)->create();
        $result = $this->make()->validate(json_encode($media->pluck('id')->all()));

        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // validate — library membership
    // -------------------------------------------------------------------------

    public function test_validate_returns_error_when_media_not_in_expected_library(): void
    {
        $libA = $this->makeLibrary('lib-a');
        $libB = $this->makeLibrary('lib-b');
        $media = Media::factory()->create(['library_id' => $libA->id]);

        $type = $this->make(['library_id' => $libB->id]);
        $result = $type->validate(json_encode([$media->id]));

        $this->assertIsString($result);
        $this->assertStringContainsString('expected library', $result);
    }

    public function test_validate_passes_when_media_belongs_to_expected_library(): void
    {
        $library = $this->makeLibrary();
        $media = Media::factory()->create(['library_id' => $library->id]);

        $type = $this->make(['library_id' => $library->id]);
        $result = $type->validate(json_encode([$media->id]));

        $this->assertTrue($result);
    }

    public function test_validate_resolves_library_by_handle(): void
    {
        $library = $this->makeLibrary('photos');
        $media = Media::factory()->create(['library_id' => $library->id]);

        $type = $this->make(['library_handle' => 'photos']);
        $result = $type->validate(json_encode([$media->id]));

        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // validate — allowed_types MIME check
    // -------------------------------------------------------------------------

    public function test_validate_returns_error_when_mime_type_not_in_allowed_types(): void
    {
        $media = Media::factory()->create(['mime_type' => 'video/mp4']);

        $type = $this->make(['allowed_types' => ['image/jpeg', 'image/png']]);
        $result = $type->validate(json_encode([$media->id]));

        $this->assertIsString($result);
        $this->assertStringContainsString('disallowed', $result);
    }

    public function test_validate_passes_when_mime_type_is_allowed(): void
    {
        $media = Media::factory()->create(['mime_type' => 'image/jpeg']);

        $type = $this->make(['allowed_types' => ['image/jpeg']]);
        $result = $type->validate(json_encode([$media->id]));

        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // H8 — value() eager-loads field values
    // -------------------------------------------------------------------------

    public function test_value_eager_loads_field_values_on_returned_media(): void
    {
        $media = Media::factory()->count(2)->create();

        $result = $this->make()->value(json_encode($media->pluck('id')->all()));

        // fieldValues relation must be loaded (not lazy) on every returned model.
        foreach ($result as $item) {
            $this->assertTrue(
                $item->relationLoaded('fieldValues'),
                "fieldValues relation should be eager-loaded on Media id={$item->id}"
            );
        }
    }

    public function test_value_incurs_no_extra_queries_per_item_for_field_values(): void
    {
        $media = Media::factory()->count(3)->create();
        $ids = json_encode($media->pluck('id')->all());

        // Warm the query — first call may hit schema inspection caches.
        $this->make()->value($ids);

        DB::enableQueryLog();
        $this->make()->value($ids);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Expect: 1 media query + up to 1 fieldValues query + up to 1 field query
        // + up to 1 fieldType query = at most 4. Never N per media item.
        $this->assertLessThanOrEqual(
            4,
            $count,
            'value() should use eager loading, not one query per media item.'
        );
    }

    // -------------------------------------------------------------------------
    // resolveLibraryId
    // -------------------------------------------------------------------------

    public function test_resolve_library_id_returns_id_from_library_setting(): void
    {
        $library = $this->makeLibrary();
        $type = $this->make(['library' => [$library->id]]);

        $id = $this->callProtected($type, 'resolveLibraryId');

        $this->assertSame($library->id, $id);
    }

    public function test_resolve_library_id_falls_back_to_legacy_library_id_setting(): void
    {
        $library = $this->makeLibrary('legacy');
        $type = $this->make(['library_id' => $library->id]);

        $id = $this->callProtected($type, 'resolveLibraryId');

        $this->assertSame($library->id, $id);
    }

    public function test_resolve_library_id_returns_null_when_no_library_configured(): void
    {
        $type = $this->make();

        $id = $this->callProtected($type, 'resolveLibraryId');

        $this->assertNull($id);
    }

    // -------------------------------------------------------------------------
    // buildAcceptString
    // -------------------------------------------------------------------------

    public function test_build_accept_string_returns_comma_separated_mimes_from_key_value_rows(): void
    {
        $type = $this->make([
            'allowed_types' => [
                ['key' => 'image/jpeg', 'label' => 'JPEG'],
                ['key' => 'image/png',  'label' => 'PNG'],
            ],
        ]);

        $accept = $this->callProtected($type, 'buildAcceptString');

        $this->assertSame('image/jpeg,image/png', $accept);
    }

    public function test_build_accept_string_returns_empty_string_when_no_allowed_types(): void
    {
        $type = $this->make();

        $accept = $this->callProtected($type, 'buildAcceptString');

        $this->assertSame('', $accept);
    }

    public function test_build_accept_string_handles_flat_string_array(): void
    {
        $type = $this->make(['allowed_types' => ['image/gif', 'video/mp4']]);

        $accept = $this->callProtected($type, 'buildAcceptString');

        $this->assertSame('image/gif,video/mp4', $accept);
    }

    // -------------------------------------------------------------------------
    // render()
    // -------------------------------------------------------------------------

    public function test_render_includes_library_upload_url(): void
    {
        $library = $this->makeLibrary('renders');
        $type = $this->make(['library' => [$library->id]]);

        $html = $type->render(['input_name' => 'fields[photo]', 'label' => 'Photo']);

        $this->assertStringContainsString((string) $library->id, $html);
    }

    public function test_render_includes_accept_attribute_when_allowed_types_set(): void
    {
        $library = $this->makeLibrary('renders2');
        $type = $this->make([
            'library' => [$library->id],
            'allowed_types' => [['key' => 'image/jpeg', 'label' => 'JPEG']],
        ]);

        $html = $type->render(['input_name' => 'fields[photo]', 'label' => 'Photo']);

        $this->assertStringContainsString('image/jpeg', $html);
    }

    public function test_render_shows_warning_when_no_library_configured(): void
    {
        $type = $this->make();

        $html = $type->render(['input_name' => 'fields[photo]', 'label' => 'Photo']);

        $this->assertStringContainsString('No library configured', $html);
    }

    public function test_render_emits_hidden_input_named_from_field_handle_when_input_name_omitted(): void
    {
        $library = $this->makeLibrary('attachments');
        $media = Media::factory()->create(['library_id' => $library->id]);

        $fieldType = Type::firstOrCreate(
            ['object' => FileUpload::class],
            ['name' => 'File Upload', 'object' => FileUpload::class]
        );
        $field = Field::factory()->create([
            'field_type_id' => $fieldType->id,
            'handle' => 'attachment',
        ]);

        $type = new FileUpload(['library' => [$library->id]], $field);
        $html = $type->render(['value' => collect([$media])]);

        $this->assertStringContainsString('name="fields[attachment][]"', $html);
    }

    public function test_render_respects_explicit_input_name_param(): void
    {
        $library = $this->makeLibrary('explicit');
        $media = Media::factory()->create(['library_id' => $library->id]);

        $fieldType = Type::firstOrCreate(
            ['object' => FileUpload::class],
            ['name' => 'File Upload', 'object' => FileUpload::class]
        );
        $field = Field::factory()->create([
            'field_type_id' => $fieldType->id,
            'handle' => 'attachment',
        ]);

        $type = new FileUpload(['library' => [$library->id]], $field);
        $html = $type->render([
            'input_name' => 'custom[name]',
            'value' => collect([$media]),
        ]);

        $this->assertStringContainsString('name="custom[name][]"', $html);
        $this->assertStringNotContainsString('name="fields[attachment][]"', $html);
    }

    /**
     * Production code path: instances created via Field::typeInstance() (not
     * direct constructor) must have the Field attached so input_name defaults
     * resolve correctly. Prior tests only covered the constructor-pass path.
     */
    public function test_render_via_field_render_path_uses_handle_for_input_name(): void
    {
        $library = $this->makeLibrary('attachments');
        $media   = Media::factory()->create(['library_id' => $library->id]);

        $fieldType = Type::firstOrCreate(
            ['object' => FileUpload::class],
            ['name' => 'File Upload', 'object' => FileUpload::class]
        );
        $field = Field::factory()->create([
            'field_type_id' => $fieldType->id,
            'handle'        => 'attachment',
            'settings'      => ['library' => [$library->id]],
        ]);

        $html = $field->render(['value' => collect([$media])]);

        $this->assertStringContainsString('name="fields[attachment][]"', $html);
    }

    public function test_render_repopulates_chips_from_old_input_after_validation_error(): void
    {
        $library = $this->makeLibrary('attachments');
        $stale   = Media::factory()->create(['library_id' => $library->id, 'original_name' => 'stale.pdf']);
        $userA   = Media::factory()->create(['library_id' => $library->id, 'original_name' => 'just-uploaded-a.pdf']);
        $userB   = Media::factory()->create(['library_id' => $library->id, 'original_name' => 'just-uploaded-b.pdf']);

        $fieldType = Type::firstOrCreate(
            ['object' => FileUpload::class],
            ['name' => 'File Upload', 'object' => FileUpload::class]
        );
        $field = Field::factory()->create([
            'field_type_id' => $fieldType->id,
            'handle'        => 'attachment',
            'settings'      => ['library' => [$library->id]],
        ]);

        // Simulate post-redirect state: bind a session to the current Request
        // (unit tests don't go through HTTP middleware that does this) and
        // flash old input as Laravel would after a withInput() redirect.
        $session = $this->app['session']->driver();
        $session->start();
        $session->put('_old_input', ['fields' => ['attachment' => [$userA->id, $userB->id]]]);
        $this->app['request']->setLaravelSession($session);

        $html = $field->render(['value' => collect([$stale])]);

        $this->assertStringContainsString('data-chip="' . $userA->id . '"', $html);
        $this->assertStringContainsString('data-chip="' . $userB->id . '"', $html);
        $this->assertStringNotContainsString('data-chip="' . $stale->id . '"', $html);
    }

    // -------------------------------------------------------------------------
    // validate() library_handle cache — single lookup per unique handle
    // -------------------------------------------------------------------------

    public function test_validate_library_handle_queries_db_only_once_per_handle(): void
    {
        $library = $this->makeLibrary('cache-test');
        $media = Media::factory()->create(['library_id' => $library->id]);
        $type = $this->make(['library_handle' => 'cache-test']);
        $ids = json_encode([$media->id]);

        $type->validate($ids); // warms the cache

        DB::enableQueryLog();
        $type->validate($ids);
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $libraryLookups = collect($log)->filter(
            fn ($q) => str_contains($q['query'], 'media_libraries') && str_contains($q['query'], 'handle')
        );

        $this->assertSame(
            0,
            $libraryLookups->count(),
            'Second validate() call must not re-query media_libraries for the same handle.'
        );
    }
}
