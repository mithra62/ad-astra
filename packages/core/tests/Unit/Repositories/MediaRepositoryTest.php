<?php

namespace Tests\Unit\Repositories;

use AdAstra\Models\Category;
use AdAstra\Models\Media;
use AdAstra\Models\Media\Library;
use AdAstra\Repositories\MediaRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private MediaRepository $repo;

    public function test_apply_data_updates_name(): void
    {
        $media = Media::factory()->create(['name' => 'Old Name']);

        $updated = $this->repo->applyData($media, ['name' => 'New Name']);

        $this->assertEquals('New Name', $updated->name);
        $this->assertDatabaseHas('media', ['id' => $media->id, 'name' => 'New Name']);
    }

    // ── applyData — core attributes ────────────────────────────────────────

    public function test_apply_data_does_not_change_name_when_not_provided(): void
    {
        $media = Media::factory()->create(['name' => 'Stable Name']);

        $updated = $this->repo->applyData($media, ['sort_order' => 3]);

        $this->assertEquals('Stable Name', $updated->name);
    }

    public function test_apply_data_updates_sort_order(): void
    {
        $media = Media::factory()->create(['sort_order' => 0]);

        $updated = $this->repo->applyData($media, ['sort_order' => 9]);

        $this->assertEquals(9, $updated->sort_order);
        $this->assertDatabaseHas('media', ['id' => $media->id, 'sort_order' => 9]);
    }

    public function test_apply_data_does_not_change_sort_order_when_key_absent(): void
    {
        $media = Media::factory()->create(['sort_order' => 5]);

        $this->repo->applyData($media, ['name' => 'Renamed']);

        $this->assertEquals(5, $media->fresh()->sort_order);
    }

    public function test_apply_data_casts_sort_order_to_integer(): void
    {
        $media = Media::factory()->create(['sort_order' => 0]);

        $updated = $this->repo->applyData($media, ['sort_order' => '7']);

        $this->assertSame(7, $updated->sort_order);
    }

    public function test_apply_data_syncs_categories(): void
    {
        $media = Media::factory()->create();
        $category = Category::factory()->create();

        $this->repo->applyData($media, ['categories' => [$category->id]]);

        $this->assertDatabaseHas('categorizables', [
            'categorizable_type' => $media->getMorphClass(),
            'categorizable_id' => $media->id,
            'category_id' => $category->id,
        ]);
    }

    // ── applyData — categories ─────────────────────────────────────────────

    public function test_apply_data_does_not_touch_categories_when_key_absent(): void
    {
        $media = Media::factory()->create();
        $category = Category::factory()->create();
        $media->categories()->attach($category->id);

        $this->repo->applyData($media, ['name' => 'Renamed']);

        $this->assertCount(1, $media->fresh()->categories);
    }

    public function test_apply_data_detaches_all_categories_when_passed_empty_array(): void
    {
        $media = Media::factory()->create();
        $category = Category::factory()->create();
        $media->categories()->attach($category->id);

        $this->repo->applyData($media, ['categories' => []]);

        $this->assertCount(0, $media->fresh()->categories);
    }

    public function test_delete_soft_deletes_media_record(): void
    {
        $media = Media::factory()->create();

        $result = $this->repo->delete($media);

        $this->assertTrue($result);
        $this->assertSoftDeleted('media', ['id' => $media->id]);
    }

    // ── delete ─────────────────────────────────────────────────────────────

    public function test_deleted_media_is_excluded_from_default_queries(): void
    {
        $media = Media::factory()->create();

        $this->repo->delete($media);

        $this->assertNull(Media::find($media->id));
        $this->assertNotNull(Media::withTrashed()->find($media->id));
    }

    public function test_resolve_layout_fields_returns_empty_collection_when_library_has_no_layout(): void
    {
        $library = Library::factory()->create(['field_layout_id' => null]);
        $media = Media::factory()->for($library, 'library')->create();

        $fields = $this->repo->resolveLayoutFields($media);

        $this->assertCount(0, $fields);
    }

    // ── resolveLayoutFields ────────────────────────────────────────────────

    public function test_resolve_layout_fields_returns_empty_collection_when_media_has_no_library(): void
    {
        $media = Media::factory()->create(['library_id' => null]);

        $fields = $this->repo->resolveLayoutFields($media);

        $this->assertCount(0, $fields);
    }

    public function test_apply_data_returns_refreshed_media_instance(): void
    {
        $media = Media::factory()->create(['name' => 'Before']);

        $result = $this->repo->applyData($media, ['name' => 'After']);

        $this->assertInstanceOf(Media::class, $result);
        $this->assertEquals('After', $result->name);
    }

    // ── return value contract ──────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new MediaRepository;
    }
}
