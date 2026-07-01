<?php

namespace Tests\Unit\Field\Types;

use AdAstra\Contracts\SyncsToMediables;
use AdAstra\Field\Types\Media;
use AdAstra\Models\Field;
use AdAstra\Models\Field\Type;
use AdAstra\Models\Media as MediaModel;
use AdAstra\Models\Media\Library;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The Media field's render() calls route('media.picker.index'); make
        // sure that name resolves under the testing route table.
        if (!Route::has('media.picker.index')) {
            Route::get('/__test/picker', fn() => null)->name('media.picker.index');
        }
    }

    private function make(array $settings = []): Media
    {
        return new Media($settings);
    }

    private function makeLibrary(string $handle = 'lib'): Library
    {
        return Library::create([
            'name' => ucfirst($handle) . ' Library',
            'handle' => $handle,
            'adapter' => 'local',
        ]);
    }

    // -------------------------------------------------------------------------
    // Contract / shape
    // -------------------------------------------------------------------------

    public function test_implements_syncs_to_mediables(): void
    {
        $this->assertInstanceOf(SyncsToMediables::class, $this->make());
    }

    public function test_storage_column_is_value_json(): void
    {
        $this->assertSame('value_json', $this->make()->storageColumn());
    }

    public function test_is_relational_returns_false(): void
    {
        $this->assertFalse($this->make()->isRelational());
    }

    // -------------------------------------------------------------------------
    // cast
    // -------------------------------------------------------------------------

    public function test_cast_decodes_json_string_to_int_array(): void
    {
        $this->assertSame([10, 20, 30], $this->make()->cast('[10, 20, 30]'));
    }

    public function test_cast_passes_through_array(): void
    {
        $this->assertSame([5, 10], $this->make()->cast(['5', '10']));
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
        $media = MediaModel::factory()->count(2)->create();
        $result = $this->make()->value(json_encode($media->pluck('id')->all()));

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
    }

    public function test_value_preserves_saved_sort_order(): void
    {
        $first = MediaModel::factory()->create();
        $second = MediaModel::factory()->create();

        $result = $this->make()->value(json_encode([$second->id, $first->id]));

        $this->assertSame($second->id, $result->first()->id);
        $this->assertSame($first->id, $result->last()->id);
    }

    public function test_value_returns_empty_collection_for_empty_input(): void
    {
        $this->assertCount(0, $this->make()->value('[]'));
    }

    // -------------------------------------------------------------------------
    // validate — min / max
    // -------------------------------------------------------------------------

    public function test_validate_returns_error_when_below_min(): void
    {
        $type = $this->make(['min' => 2]);
        $media = MediaModel::factory()->create();

        $result = $type->validate(json_encode([$media->id]));

        $this->assertIsString($result);
        $this->assertStringContainsString('2', $result);
    }

    public function test_validate_returns_error_when_above_max(): void
    {
        $type = $this->make(['max' => 1]);
        $media = MediaModel::factory()->count(2)->create();

        $result = $type->validate(json_encode($media->pluck('id')->all()));

        $this->assertIsString($result);
    }

    public function test_validate_passes_with_no_constraints_and_empty_value(): void
    {
        $this->assertTrue($this->make()->validate('[]'));
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

    // -------------------------------------------------------------------------
    // validate — library scope (allowed_libraries)
    // -------------------------------------------------------------------------

    public function test_validate_rejects_media_outside_allowed_libraries(): void
    {
        $libA = $this->makeLibrary('a');
        $libB = $this->makeLibrary('b');
        $mediaInB = MediaModel::factory()->create(['library_id' => $libB->id]);

        $type = $this->make(['libraries' => [$libA->id]]);
        $result = $type->validate(json_encode([$mediaInB->id]));

        $this->assertIsString($result);
        $this->assertStringContainsString('allowed library', $result);
    }

    public function test_validate_passes_when_media_is_in_an_allowed_library(): void
    {
        $libA = $this->makeLibrary('a');
        $libB = $this->makeLibrary('b');
        $mA = MediaModel::factory()->create(['library_id' => $libA->id]);
        $mB = MediaModel::factory()->create(['library_id' => $libB->id]);

        $type = $this->make(['libraries' => [$libA->id, $libB->id]]);
        $result = $type->validate(json_encode([$mA->id, $mB->id]));

        $this->assertTrue($result);
    }

    public function test_validate_allows_cross_library_selection(): void
    {
        $libA = $this->makeLibrary('a');
        $libB = $this->makeLibrary('b');
        $mA = MediaModel::factory()->create(['library_id' => $libA->id]);
        $mB = MediaModel::factory()->create(['library_id' => $libB->id]);

        $type = $this->make(['libraries' => [$libA->id, $libB->id]]);
        $this->assertTrue($type->validate(json_encode([$mA->id, $mB->id])));
    }

    // -------------------------------------------------------------------------
    // settings shape
    // -------------------------------------------------------------------------

    public function test_libraries_setting_rule_requires_at_least_one(): void
    {
        $rules = $this->make()->settingsRules();

        $this->assertSame('required|array|min:1', $rules['settings.libraries']);
    }

    public function test_settings_form_options_returns_library_list(): void
    {
        $this->makeLibrary('photos');
        $this->makeLibrary('docs');

        $options = $this->make()->settingsFormOptions();

        $this->assertArrayHasKey('libraries', $options);
        $this->assertCount(2, $options['libraries']);
        foreach ($options['libraries'] as $opt) {
            $this->assertArrayHasKey('value', $opt);
            $this->assertArrayHasKey('label', $opt);
        }
    }

    // -------------------------------------------------------------------------
    // render()
    // -------------------------------------------------------------------------

    public function test_render_includes_browse_button_when_libraries_configured(): void
    {
        $lib = $this->makeLibrary('photos');
        $type = $this->make(['libraries' => [$lib->id]]);

        $html = $type->render(['input_name' => 'fields[gallery]']);

        $this->assertStringContainsString('Browse / Upload', $html);
        $this->assertStringContainsString((string)$lib->id, $html);
    }

    public function test_render_shows_warning_when_no_libraries_configured(): void
    {
        $type = $this->make(['libraries' => []]);

        $html = $type->render(['input_name' => 'fields[gallery]']);

        $this->assertStringContainsString('No libraries configured', $html);
    }

    // -------------------------------------------------------------------------
    // render() — input_name default
    //
    // Most admin views (users/edit, categories/edit, entries) don't pass
    // input_name to field.render(). Without a default, hidden inputs end up
    // named `[]` and the field key is missing from the submitted payload —
    // which makes any `required` check fail even when media is selected.
    // -------------------------------------------------------------------------

    private function makeFieldWithHandle(string $handle): Field
    {
        $fieldType = Type::firstOrCreate(
            ['object' => Media::class],
            ['name' => 'Media', 'object' => Media::class]
        );

        return Field::factory()->create([
            'field_type_id' => $fieldType->id,
            'handle' => $handle,
        ]);
    }

    public function test_render_emits_hidden_input_named_from_field_handle_when_input_name_omitted(): void
    {
        $lib = $this->makeLibrary('photos');
        $media = MediaModel::factory()->create(['library_id' => $lib->id, 'original_name' => 'p.jpg']);
        $field = $this->makeFieldWithHandle('gallery');

        $type = new Media(['libraries' => [$lib->id]], $field);
        $html = $type->render(['value' => collect([$media])]);

        $this->assertStringContainsString('name="fields[gallery][]"', $html);
    }

    public function test_render_respects_explicit_input_name_param(): void
    {
        $lib = $this->makeLibrary('photos');
        $media = MediaModel::factory()->create(['library_id' => $lib->id, 'original_name' => 'p.jpg']);
        $field = $this->makeFieldWithHandle('gallery');

        $type = new Media(['libraries' => [$lib->id]], $field);
        $html = $type->render(['value' => collect([$media]), 'input_name' => 'custom[name]']);

        $this->assertStringContainsString('name="custom[name][]"', $html);
        $this->assertStringNotContainsString('name="fields[gallery][]"', $html);
    }

    /**
     * Verifies the production code path that actually breaks today: instances
     * created via Field::typeInstance() (not the direct constructor) must have
     * their owning Field attached so input_name defaulting works. The prior
     * tests only exercised the constructor-pass path.
     */
    public function test_render_via_field_render_path_uses_handle_for_input_name(): void
    {
        $lib = $this->makeLibrary('photos');
        $field = $this->makeFieldWithHandle('gallery');
        $field->settings = ['libraries' => [$lib->id]];
        $field->save();

        // Call Field::render() — the exact path admin views use. Pre-populate
        // a value so the chip rendering exercises the hidden-input name.
        $media = MediaModel::factory()->create(['library_id' => $lib->id, 'original_name' => 'p.jpg']);
        $html = $field->render(['value' => collect([$media])]);

        $this->assertStringContainsString('name="fields[gallery][]"', $html);
    }

    public function test_render_repopulates_chips_from_old_input_after_validation_error(): void
    {
        $lib = $this->makeLibrary('photos');
        $field = $this->makeFieldWithHandle('gallery');
        $field->settings = ['libraries' => [$lib->id]];
        $field->save();

        $stale = MediaModel::factory()->create(['library_id' => $lib->id, 'original_name' => 'stale.jpg']);
        $userA = MediaModel::factory()->create(['library_id' => $lib->id, 'original_name' => 'just-uploaded-a.jpg']);
        $userB = MediaModel::factory()->create(['library_id' => $lib->id, 'original_name' => 'just-uploaded-b.jpg']);

        // Simulate post-redirect state: bind a session to the current Request
        // (unit tests don't go through HTTP middleware that does this) and
        // flash old input as Laravel would after a withInput() redirect.
        $session = $this->app['session']->driver();
        $session->start();
        $session->put('_old_input', ['fields' => ['gallery' => [$userA->id, $userB->id]]]);
        $this->app['request']->setLaravelSession($session);

        // The caller passes the persisted value (stale); old() should win.
        $html = $field->render(['value' => collect([$stale])]);

        $this->assertStringContainsString('data-chip="' . $userA->id . '"', $html);
        $this->assertStringContainsString('data-chip="' . $userB->id . '"', $html);
        $this->assertStringNotContainsString('data-chip="' . $stale->id . '"', $html);
    }
}
