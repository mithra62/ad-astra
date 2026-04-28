<?php

namespace Tests\Unit\Actions\Media\Library;

use App\Actions\Media\Library\CreateNewMediaLibrary;
use App\Actions\Media\Library\DeleteMediaLibrary;
use App\Actions\Media\Library\EditMediaLibrary;
use App\Models\Category\Group as CategoryGroup;
use App\Models\Field\Group as FieldGroup;
use App\Models\FieldLayout;
use App\Models\Media\Library;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaLibraryActionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeLibraryData(array $overrides = []): array
    {
        return array_merge([
            'name'   => 'Test Library',
            'handle' => 'test-library',
            'adapter' => 'local',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // CreateNewMediaLibrary
    // -------------------------------------------------------------------------

    public function test_create_returns_library_instance(): void
    {
        $action = app(CreateNewMediaLibrary::class);

        $result = $action->create($this->makeLibraryData());

        $this->assertInstanceOf(Library::class, $result);
    }

    public function test_create_persists_library_to_database(): void
    {
        $action = app(CreateNewMediaLibrary::class);

        $action->create($this->makeLibraryData(['name' => 'Uploads', 'handle' => 'uploads']));

        $this->assertDatabaseHas('media_libraries', [
            'name'   => 'Uploads',
            'handle' => 'uploads',
        ]);
    }

    public function test_create_stores_adapter(): void
    {
        $action = app(CreateNewMediaLibrary::class);

        $library = $action->create($this->makeLibraryData(['adapter' => 'local']));

        $this->assertEquals('local', $library->adapter);
    }

    public function test_create_attaches_category_groups_when_provided(): void
    {
        $catGroup = CategoryGroup::factory()->create();
        $action   = app(CreateNewMediaLibrary::class);

        $library = $action->create($this->makeLibraryData([
            'category_groups' => [$catGroup->id],
        ]));

        $this->assertTrue($library->category_groups()->where('group_id', $catGroup->id)->exists());
    }

    public function test_create_attaches_multiple_category_groups(): void
    {
        $catGroup1 = CategoryGroup::factory()->create();
        $catGroup2 = CategoryGroup::factory()->create();
        $action    = app(CreateNewMediaLibrary::class);

        $library = $action->create($this->makeLibraryData([
            'category_groups' => [$catGroup1->id, $catGroup2->id],
        ]));

        $this->assertTrue($library->category_groups()->where('group_id', $catGroup1->id)->exists());
        $this->assertTrue($library->category_groups()->where('group_id', $catGroup2->id)->exists());
    }

    public function test_create_with_no_category_groups_produces_no_attachments(): void
    {
        $action  = app(CreateNewMediaLibrary::class);
        $library = $action->create($this->makeLibraryData());

        $this->assertCount(0, $library->category_groups);
    }

    // -------------------------------------------------------------------------
    // DeleteMediaLibrary
    // -------------------------------------------------------------------------

    public function test_delete_removes_library_from_database(): void
    {
        $library = Library::create($this->makeLibraryData(['handle' => 'to-delete']));
        $action  = app(DeleteMediaLibrary::class);

        $action->delete($library);

        $this->assertDatabaseMissing('media_libraries', ['id' => $library->id]);
    }

    public function test_delete_returns_true(): void
    {
        $library = Library::create($this->makeLibraryData(['handle' => 'del-true']));
        $action  = app(DeleteMediaLibrary::class);

        $result = $action->delete($library);

        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // EditMediaLibrary
    // -------------------------------------------------------------------------

    public function test_edit_returns_true_on_success(): void
    {
        $library = Library::create($this->makeLibraryData(['handle' => 'edit-true']));
        $action  = app(EditMediaLibrary::class);

        $result = $action->edit($library, ['name' => 'Updated', 'handle' => 'updated']);

        $this->assertTrue($result);
    }

    public function test_edit_updates_library_name_and_handle(): void
    {
        $library = Library::create($this->makeLibraryData(['name' => 'Old', 'handle' => 'old']));
        $action  = app(EditMediaLibrary::class);

        $action->edit($library, ['name' => 'New Library', 'handle' => 'new-library']);

        $this->assertDatabaseHas('media_libraries', [
            'id'     => $library->id,
            'name'   => 'New Library',
            'handle' => 'new-library',
        ]);
    }

    public function test_edit_replaces_category_groups(): void
    {
        $catGroup1 = CategoryGroup::factory()->create();
        $catGroup2 = CategoryGroup::factory()->create();
        $library   = Library::create($this->makeLibraryData(['handle' => 'cat-replace']));
        $library->category_groups()->attach($catGroup1->id);
        $action    = app(EditMediaLibrary::class);

        $action->edit($library, [
            'name'            => $library->name,
            'handle'          => $library->handle,
            'category_groups' => [$catGroup2->id],
        ]);

        $fresh = $library->fresh();
        $this->assertFalse($fresh->category_groups()->where('group_id', $catGroup1->id)->exists());
        $this->assertTrue($fresh->category_groups()->where('group_id', $catGroup2->id)->exists());
    }

    public function test_edit_detaches_all_category_groups_when_none_provided(): void
    {
        $catGroup = CategoryGroup::factory()->create();
        $library  = Library::create($this->makeLibraryData(['handle' => 'cat-detach']));
        $library->category_groups()->attach($catGroup->id);
        $action   = app(EditMediaLibrary::class);

        $action->edit($library, ['name' => $library->name, 'handle' => $library->handle]);

        $this->assertCount(0, $library->fresh()->category_groups);
    }

    public function test_edit_replaces_field_groups(): void
    {
        $fieldGroup1 = FieldGroup::factory()->create();
        $fieldGroup2 = FieldGroup::factory()->create();
        $library     = Library::create($this->makeLibraryData(['handle' => 'fg-replace']));
        $library->field_groups()->attach($fieldGroup1->id);
        $action      = app(EditMediaLibrary::class);

        $action->edit($library, [
            'name'         => $library->name,
            'handle'       => $library->handle,
            'field_groups' => [$fieldGroup2->id],
        ]);

        $fresh = $library->fresh();
        $this->assertFalse($fresh->field_groups()->where('group_id', $fieldGroup1->id)->exists());
        $this->assertTrue($fresh->field_groups()->where('group_id', $fieldGroup2->id)->exists());
    }

    public function test_edit_detaches_all_field_groups_when_none_provided(): void
    {
        $fieldGroup = FieldGroup::factory()->create();
        $library    = Library::create($this->makeLibraryData(['handle' => 'fg-detach']));
        $library->field_groups()->attach($fieldGroup->id);
        $action     = app(EditMediaLibrary::class);

        $action->edit($library, ['name' => $library->name, 'handle' => $library->handle]);

        $this->assertCount(0, $library->fresh()->field_groups);
    }
}
