<?php

namespace Tests\Unit\Repositories;

use App\EntryTypes\AbstractEntryType;
use App\Models\Category;
use App\Models\Category\Group as CategoryGroup;
use App\Models\Entry;
use App\Models\EntryGroup;
use App\Models\EntryRelationship;
use App\Models\EntryType;
use App\Models\Field;
use App\Models\Status;
use App\Models\StatusGroup;
use App\Models\User;
use App\Repositories\EntryRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EntryRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new EntryRepository;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeEntryGroup(?StatusGroup $statusGroup = null): EntryGroup
    {
        return EntryGroup::factory()->create([
            'status_group_id' => $statusGroup?->id,
        ]);
    }

    private function makeEntryType(EntryGroup $group): EntryType
    {
        return EntryType::factory()->create([
            'entry_group_id' => $group->id,
            'class' => 'App\\EntryTypes\\BlogPostEntryType',
        ]);
    }

    private function makeStatusGroup(): StatusGroup
    {
        $statusGroup = StatusGroup::factory()->create();
        Status::factory()->default()->create(['status_group_id' => $statusGroup->id, 'handle' => 'draft']);

        return $statusGroup;
    }

    private function makeAbstractEntryType(EntryType $record): AbstractEntryType
    {
        return new class($record) extends AbstractEntryType {};
    }

    // -----------------------------------------------------------------------
    // create()
    // -----------------------------------------------------------------------

    public function test_create_persists_entry_and_returns_instance(): void
    {
        $statusGroup = $this->makeStatusGroup();
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $user = User::factory()->create();
        $this->actingAs($user);

        $entryType = $this->makeAbstractEntryType($type);

        $entry = $this->repo->create($entryType, ['title' => 'Hello World']);

        $this->assertInstanceOf(Entry::class, $entry);
        $this->assertTrue($entry->exists);
        $this->assertDatabaseHas('entries', ['id' => $entry->id, 'title' => 'Hello World']);
    }

    public function test_create_sets_created_by_user_id_from_auth(): void
    {
        $statusGroup = $this->makeStatusGroup();
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $user = User::factory()->create();
        $this->actingAs($user);

        $entryType = $this->makeAbstractEntryType($type);

        $entry = $this->repo->create($entryType, ['title' => 'Authored']);

        $this->assertEquals($user->id, $entry->created_by_user_id);
    }

    public function test_create_applies_default_status_from_status_group(): void
    {
        $statusGroup = $this->makeStatusGroup();
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $this->actingAs(User::factory()->create());

        $entryType = $this->makeAbstractEntryType($type);

        $entry = $this->repo->create($entryType, ['title' => 'Draft Entry']);

        $this->assertEquals('draft', $entry->status);
    }

    public function test_create_uses_explicit_status_when_provided(): void
    {
        $statusGroup = $this->makeStatusGroup();
        Status::factory()->create(['status_group_id' => $statusGroup->id, 'handle' => 'published', 'is_default' => false]);
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $this->actingAs(User::factory()->create());

        $entryType = $this->makeAbstractEntryType($type);

        $entry = $this->repo->create($entryType, ['title' => 'Live', 'status' => 'published']);

        $this->assertEquals('published', $entry->status);
    }

    public function test_create_auto_generates_handle_from_title(): void
    {
        $statusGroup = $this->makeStatusGroup();
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $this->actingAs(User::factory()->create());

        $entryType = $this->makeAbstractEntryType($type);

        $entry = $this->repo->create($entryType, ['title' => 'My Blog Post']);

        $this->assertEquals('my-blog-post', $entry->handle);
    }

    public function test_create_uses_explicit_handle_when_provided(): void
    {
        $statusGroup = $this->makeStatusGroup();
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $this->actingAs(User::factory()->create());

        $entryType = $this->makeAbstractEntryType($type);

        $entry = $this->repo->create($entryType, ['title' => 'Title', 'handle' => 'custom-handle']);

        $this->assertEquals('custom-handle', $entry->handle);
    }

    public function test_create_throws_when_entry_group_has_no_status_group(): void
    {
        $group = $this->makeEntryGroup(null);
        $type = $this->makeEntryType($group);
        $this->actingAs(User::factory()->create());

        $entryType = $this->makeAbstractEntryType($type);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no status group/i');

        $this->repo->create($entryType, ['title' => 'Broken']);
    }

    public function test_create_syncs_authors(): void
    {
        $statusGroup = $this->makeStatusGroup();
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $user = User::factory()->create();
        $author = User::factory()->create();
        $this->actingAs($user);

        $entryType = $this->makeAbstractEntryType($type);

        $entry = $this->repo->create($entryType, [
            'title' => 'With Authors',
            'authors' => [$author->id],
        ]);

        $this->assertDatabaseHas('entry_authors', ['entry_id' => $entry->id, 'user_id' => $author->id]);
    }

    public function test_create_syncs_categories(): void
    {
        $statusGroup = $this->makeStatusGroup();
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $this->actingAs(User::factory()->create());

        $catGroup = CategoryGroup::factory()->create();
        $category = Category::factory()->for($catGroup, 'group')->create();

        $entryType = $this->makeAbstractEntryType($type);

        $entry = $this->repo->create($entryType, [
            'title' => 'Categorised',
            'categories' => [$category->id],
        ]);

        $this->assertDatabaseHas('categorizables', [
            'category_id' => $category->id,
            'categorizable_id' => $entry->id,
        ]);
    }

    // -----------------------------------------------------------------------
    // find() / findOrFail() / findByHandle() / findOrFailByHandle()
    // -----------------------------------------------------------------------

    public function test_find_returns_entry_when_it_exists(): void
    {
        $entry = Entry::factory()->create();

        $result = $this->repo->find($entry->id);

        $this->assertNotNull($result);
        $this->assertEquals($entry->id, $result->id);
    }

    public function test_find_returns_null_when_entry_does_not_exist(): void
    {
        $this->assertNull($this->repo->find(999999));
    }

    public function test_find_or_fail_returns_entry_when_it_exists(): void
    {
        $entry = Entry::factory()->create();

        $result = $this->repo->findOrFail($entry->id);

        $this->assertEquals($entry->id, $result->id);
    }

    public function test_find_or_fail_throws_when_entry_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repo->findOrFail(999999);
    }

    public function test_find_by_handle_returns_correct_entry(): void
    {
        $group = EntryGroup::factory()->create();
        $entry = Entry::factory()->create(['entry_group_id' => $group->id, 'handle' => 'my-post']);

        $result = $this->repo->findByHandle('my-post', $group);

        $this->assertNotNull($result);
        $this->assertEquals($entry->id, $result->id);
    }

    public function test_find_by_handle_returns_null_when_not_found(): void
    {
        $group = EntryGroup::factory()->create();

        $this->assertNull($this->repo->findByHandle('nonexistent', $group));
    }

    public function test_find_or_fail_by_handle_throws_when_not_found(): void
    {
        $group = EntryGroup::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        $this->repo->findOrFailByHandle('nonexistent', $group);
    }

    // -----------------------------------------------------------------------
    // applyData()
    // -----------------------------------------------------------------------

    public function test_apply_data_updates_title(): void
    {
        $statusGroup = $this->makeStatusGroup();
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
            'title' => 'Old Title',
        ]);

        $updated = $this->repo->applyData($entry, ['title' => 'New Title']);

        $this->assertEquals('New Title', $updated->title);
        $this->assertDatabaseHas('entries', ['id' => $entry->id, 'title' => 'New Title']);
    }

    public function test_apply_data_updates_published_at(): void
    {
        $statusGroup = $this->makeStatusGroup();
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
            'published_at' => null,
        ]);

        $publishedAt = now()->toDateTimeString();
        $updated = $this->repo->applyData($entry, ['published_at' => $publishedAt]);

        $this->assertNotNull($updated->published_at);
    }

    public function test_apply_data_ignores_published_at_when_key_absent(): void
    {
        $statusGroup = $this->makeStatusGroup();
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
            'published_at' => null,
        ]);

        $this->repo->applyData($entry, ['title' => 'No Date Change']);

        $this->assertNull($entry->fresh()->published_at);
    }

    public function test_apply_data_updates_status(): void
    {
        $statusGroup = $this->makeStatusGroup();
        Status::factory()->create(['status_group_id' => $statusGroup->id, 'handle' => 'published']);
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
            'status' => 'draft',
        ]);

        $updated = $this->repo->applyData($entry, ['status' => 'published']);

        $this->assertEquals('published', $updated->status);
    }

    public function test_apply_data_updates_authors(): void
    {
        $statusGroup = $this->makeStatusGroup();
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $entry = Entry::factory()->create(['entry_group_id' => $group->id, 'entry_type_id' => $type->id]);
        $author = User::factory()->create();

        $this->repo->applyData($entry, ['authors' => [$author->id]]);

        $this->assertDatabaseHas('entry_authors', ['entry_id' => $entry->id, 'user_id' => $author->id]);
    }

    public function test_apply_data_syncs_categories(): void
    {
        $statusGroup = $this->makeStatusGroup();
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $entry = Entry::factory()->create(['entry_group_id' => $group->id, 'entry_type_id' => $type->id]);
        $catGroup = CategoryGroup::factory()->create();
        $category = Category::factory()->for($catGroup, 'group')->create();

        $this->repo->applyData($entry, ['categories' => [$category->id]]);

        $this->assertDatabaseHas('categorizables', [
            'category_id' => $category->id,
            'categorizable_id' => $entry->id,
        ]);
    }

    // -----------------------------------------------------------------------
    // delete()
    // -----------------------------------------------------------------------

    public function test_delete_removes_entry_from_database(): void
    {
        $entry = Entry::factory()->create();

        $result = $this->repo->delete($entry);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('entries', ['id' => $entry->id]);
    }

    // -----------------------------------------------------------------------
    // findMeta() / findMetaOrFail()
    // -----------------------------------------------------------------------

    public function test_find_meta_returns_entry_when_it_exists(): void
    {
        $entry = Entry::factory()->create();

        $result = $this->repo->findMeta($entry->id);

        $this->assertNotNull($result);
        $this->assertEquals($entry->id, $result->id);
    }

    public function test_find_meta_returns_null_when_entry_does_not_exist(): void
    {
        $this->assertNull($this->repo->findMeta(999999));
    }

    public function test_find_meta_or_fail_throws_when_entry_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repo->findMetaOrFail(999999);
    }

    // -----------------------------------------------------------------------
    // resolveLayoutFields()
    // -----------------------------------------------------------------------

    public function test_resolve_layout_fields_returns_empty_when_no_layouts(): void
    {
        $group = EntryGroup::factory()->create(['field_layout_id' => null]);
        $type = EntryType::factory()->create(['entry_group_id' => $group->id, 'field_layout_id' => null]);
        $entry = Entry::factory()->create(['entry_group_id' => $group->id, 'entry_type_id' => $type->id]);

        $fields = $this->repo->resolveLayoutFields($entry);

        $this->assertCount(0, $fields);
    }

    // -----------------------------------------------------------------------
    // Relationship field (syncRelationshipField via applyData)
    // -----------------------------------------------------------------------

    public function test_apply_data_filters_self_reference_from_relationship_field(): void
    {
        $statusGroup = $this->makeStatusGroup();
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $entry = Entry::factory()->create(['entry_group_id' => $group->id, 'entry_type_id' => $type->id]);
        $other = Entry::factory()->create(['entry_group_id' => $group->id, 'entry_type_id' => $type->id]);

        // Create an EntryRelationship manually to simulate prior state
        $field = Field::factory()->create();
        EntryRelationship::create([
            'entry_id' => $entry->id,
            'related_entry_id' => $other->id,
            'field_id' => $field->id,
            'sort_order' => 0,
        ]);

        // Delete via applyData with empty related ids for the relationship field
        $this->repo->applyData($entry, []);

        // Existing relationship should remain untouched since no fields key provided
        $this->assertDatabaseHas('entry_relationships', [
            'entry_id' => $entry->id,
            'related_entry_id' => $other->id,
        ]);
    }
}
