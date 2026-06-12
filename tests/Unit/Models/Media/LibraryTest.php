<?php

namespace Tests\Unit\Models\Media;

use App\Models\Category\Group as CategoryGroup;
use App\Models\Media\Library;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Fillable, Casts, Table
    // -------------------------------------------------------------------------

    public function test_has_correct_fillable_attributes(): void
    {
        $model = new Library();

        $this->assertEquals(
            ['field_layout_id', 'status_group_id', 'name', 'handle', 'adapter', 'adapter_settings', 'allowed_types', 'max_size', 'sort_order'],
            $model->getFillable()
        );
    }

    public function test_uses_media_libraries_table(): void
    {
        $this->assertEquals('media_libraries', (new Library())->getTable());
    }

    public function test_casts_sort_order_to_integer(): void
    {
        $library = Library::create(['name' => 'Images', 'handle' => 'images', 'sort_order' => '5']);

        $this->assertIsInt($library->sort_order);
        $this->assertEquals(5, $library->sort_order);
    }

    public function test_casts_adapter_settings_to_array(): void
    {
        $library = Library::create([
            'name'             => 'Videos',
            'handle'           => 'videos',
            'adapter_settings' => ['bucket' => 'my-bucket'],
        ]);

        $this->assertIsArray($library->fresh()->adapter_settings);
        $this->assertEquals('my-bucket', $library->fresh()->adapter_settings['bucket']);
    }

    public function test_adapter_settings_can_be_null(): void
    {
        $library = Library::create(['name' => 'Docs', 'handle' => 'docs']);

        $this->assertNull($library->fresh()->adapter_settings);
    }

    public function test_casts_allowed_types_to_array(): void
    {
        $library = Library::create([
            'name'          => 'Documents',
            'handle'        => 'documents',
            'allowed_types' => ['pdf', 'docx'],
        ]);

        $this->assertIsArray($library->fresh()->allowed_types);
        $this->assertContains('pdf', $library->fresh()->allowed_types);
        $this->assertContains('docx', $library->fresh()->allowed_types);
    }

    public function test_allowed_types_can_be_null(): void
    {
        $library = Library::create(['name' => 'Icons', 'handle' => 'icons']);

        $this->assertNull($library->fresh()->allowed_types);
    }

    public function test_casts_max_size_to_integer(): void
    {
        $library = Library::create(['name' => 'Assets', 'handle' => 'assets', 'max_size' => '20']);

        $this->assertIsInt($library->max_size);
        $this->assertEquals(20, $library->max_size);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function test_category_groups_relationship_is_morph_to_many(): void
    {
        $this->assertInstanceOf(MorphToMany::class, (new Library())->categoryGroups());
    }

    public function test_category_groups_is_related_to_category_group_model(): void
    {
        $this->assertInstanceOf(CategoryGroup::class, (new Library())->categoryGroups()->getRelated());
    }

    public function test_media_relationship_is_has_many(): void
    {
        $this->assertInstanceOf(HasMany::class, (new Library())->media());
    }

    public function test_field_layout_relationship_is_belongs_to(): void
    {
        $this->assertInstanceOf(BelongsTo::class, (new Library())->fieldLayout());
    }

    public function test_category_groups_can_be_attached_and_retrieved(): void
    {
        $library = Library::create(['name' => 'Gallery', 'handle' => 'gallery']);
        $group   = CategoryGroup::factory()->create();

        $library->categoryGroups()->attach($group->id);

        $this->assertCount(1, $library->fresh()->categoryGroups);
        $this->assertEquals($group->id, $library->fresh()->categoryGroups->first()->id);
    }

}
