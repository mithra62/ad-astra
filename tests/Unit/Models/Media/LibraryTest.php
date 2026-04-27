<?php

namespace Tests\Unit\Models\Media;

use App\Models\Category;
use App\Models\Category\Group as CategoryGroup;
use App\Models\Field\Group as FieldGroup;
use App\Models\Media\Library;
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
        $model = new Library;

        $this->assertEquals(
            ['name', 'handle', 'adapter', 'adapter_settings', 'allowed_types', 'max_size', 'sort_order'],
            $model->getFillable()
        );
    }

    public function test_uses_media_libraries_table(): void
    {
        $this->assertEquals('media_libraries', (new Library)->getTable());
    }

    public function test_casts_sort_order_to_integer(): void
    {
        $library = Library::create([
            'name'       => 'Images',
            'handle'     => 'images',
            'sort_order' => '5',
        ]);

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
        $library = Library::create([
            'name'   => 'Docs',
            'handle' => 'docs',
        ]);

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
        $library = Library::create([
            'name'   => 'Icons',
            'handle' => 'icons',
        ]);

        $this->assertNull($library->fresh()->allowed_types);
    }

    public function test_casts_max_size_to_integer(): void
    {
        $library = Library::create([
            'name'     => 'Assets',
            'handle'   => 'assets',
            'max_size' => '20',
        ]);

        $this->assertIsInt($library->max_size);
        $this->assertEquals(20, $library->max_size);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function test_category_groups_relationship_is_morph_to_many(): void
    {
        $library = new Library;

        $this->assertInstanceOf(MorphToMany::class, $library->category_groups());
    }

    public function test_category_groups_is_related_to_category_group_model(): void
    {
        $library = new Library;
        $relation = $library->category_groups();

        $this->assertInstanceOf(CategoryGroup::class, $relation->getRelated());
    }

    public function test_field_groups_relationship_is_morph_to_many(): void
    {
        $library = new Library;

        $this->assertInstanceOf(MorphToMany::class, $library->field_groups());
    }

    public function test_field_groups_is_related_to_field_group_model(): void
    {
        $library = new Library;
        $relation = $library->field_groups();

        $this->assertInstanceOf(FieldGroup::class, $relation->getRelated());
    }

    public function test_category_groups_can_be_attached_and_retrieved(): void
    {
        $library = Library::create(['name' => 'Gallery', 'handle' => 'gallery']);
        $group   = CategoryGroup::factory()->create();

        $library->category_groups()->attach($group->id);

        $this->assertCount(1, $library->fresh()->category_groups);
        $this->assertEquals($group->id, $library->fresh()->category_groups->first()->id);
    }

    public function test_field_groups_can_be_attached_and_retrieved(): void
    {
        $library = Library::create(['name' => 'Gallery', 'handle' => 'gallery']);
        $group   = FieldGroup::factory()->create();

        $library->field_groups()->attach($group->id);

        $this->assertCount(1, $library->fresh()->field_groups);
        $this->assertEquals($group->id, $library->fresh()->field_groups->first()->id);
    }

    // -------------------------------------------------------------------------
    // categories() helper
    // -------------------------------------------------------------------------

    public function test_categories_returns_empty_collection_when_no_category_groups(): void
    {
        $library = new Library;
        $library->setRelation('category_groups', collect([]));

        $result = $library->categories();

        $this->assertCount(0, $result);
    }

    public function test_categories_returns_all_categories_across_attached_groups(): void
    {
        $library = Library::create(['name' => 'Gallery', 'handle' => 'gallery']);

        $group1 = CategoryGroup::factory()->create();
        $group2 = CategoryGroup::factory()->create();

        $cat1 = Category::factory()->for($group1, 'group')->create();
        $cat2 = Category::factory()->for($group1, 'group')->create();
        $cat3 = Category::factory()->for($group2, 'group')->create();

        $library->category_groups()->attach([$group1->id, $group2->id]);

        $library->load('category_groups.categories');
        $categories = $library->categories();

        $this->assertCount(3, $categories);
        $this->assertTrue($categories->contains($cat1));
        $this->assertTrue($categories->contains($cat2));
        $this->assertTrue($categories->contains($cat3));
    }

    public function test_categories_excludes_categories_from_unattached_groups(): void
    {
        $library = Library::create(['name' => 'Gallery', 'handle' => 'gallery']);

        $attachedGroup   = CategoryGroup::factory()->create();
        $unattachedGroup = CategoryGroup::factory()->create();

        $attachedCat   = Category::factory()->for($attachedGroup, 'group')->create();
        $unattachedCat = Category::factory()->for($unattachedGroup, 'group')->create();

        $library->category_groups()->attach($attachedGroup->id);

        $library->load('category_groups.categories');
        $categories = $library->categories();

        $this->assertTrue($categories->contains($attachedCat));
        $this->assertFalse($categories->contains($unattachedCat));
    }
}
