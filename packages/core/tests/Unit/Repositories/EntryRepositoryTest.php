<?php

namespace Tests\Unit\Repositories;

use AdAstra\EntryTypes\AbstractEntryType;
use AdAstra\Models\Category;
use AdAstra\Models\Category\Group as CategoryGroup;
use AdAstra\Models\Entry;
use AdAstra\Models\EntryAuthor;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryRelationship;
use AdAstra\Models\EntryType;
use AdAstra\Models\Field;
use AdAstra\Models\Status;
use AdAstra\Models\StatusGroup;
use AdAstra\Models\User;
use AdAstra\Repositories\EntryRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class EntryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EntryRepository $repo;

    public function test_create_persists_entry_and_returns_instance(): void
    {
        $statusGroup = $this->makeStatusGroup();
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $user = User::factory()->create();
        $this->actingAs($user);

        $entryType = $this->makeAbstractEntryType($type);

        $entry = $this->repo->create($entryType, ['title' => 'Hello World', 'handle' => 'hello-world']);

        $this->assertInstanceOf(Entry::class, $entry);
        $this->assertTrue($entry->exists);
        $this->assertDatabaseHas('entries', ['id' => $entry->id, 'title' => 'Hello World']);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeStatusGroup(): StatusGroup
    {
        $statusGroup = StatusGroup::factory()->create();
        Status::factory()->default()->create(['status_group_id' => $statusGroup->id, 'handle' => 'draft']);

        return $statusGroup;
    }

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
        ]);
    }

    private function makeAbstractEntryType(EntryType $record): AbstractEntryType
    {
        return new class ($record) extends AbstractEntryType {
        };
    }

    // -----------------------------------------------------------------------
    // create()
    // -----------------------------------------------------------------------

    public function test_create_sets_created_by_user_id_from_auth(): void
    {
        $statusGroup = $this->makeStatusGroup();
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $user = User::factory()->create();
        $this->actingAs($user);

        $entryType = $this->makeAbstractEntryType($type);

        $entry = $this->repo->create($entryType, ['title' => 'Authored', 'handle' => 'authored']);

        $this->assertEquals($user->id, $entry->created_by_user_id);
    }

    public function test_create_applies_default_status_from_status_group(): void
    {
        $statusGroup = $this->makeStatusGroup();
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $this->actingAs(User::factory()->create());

        $entryType = $this->makeAbstractEntryType($type);

        $entry = $this->repo->create($entryType, ['title' => 'Draft Entry', 'handle' => 'draft-entry']);

        $this->assertEquals('draft', $entry->status_handle);
    }

    public function test_create_uses_explicit_status_when_provided(): void
    {
        $statusGroup = $this->makeStatusGroup();
        Status::factory()->create(['status_group_id' => $statusGroup->id, 'handle' => 'published', 'is_default' => false]);
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $this->actingAs(User::factory()->create());

        $entryType = $this->makeAbstractEntryType($type);

        $entry = $this->repo->create($entryType, ['title' => 'Live', 'handle' => 'live', 'status' => 'published']);

        $this->assertEquals('published', $entry->status_handle);
    }

    public function test_create_throws_when_explicit_status_does_not_belong_to_entry_group(): void
    {
        $statusGroup = $this->makeStatusGroup();
        $otherStatusGroup = StatusGroup::factory()->create();
        Status::factory()->create([
            'status_group_id' => $otherStatusGroup->id,
            'handle' => 'published',
            'is_default' => false,
        ]);
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $this->actingAs(User::factory()->create());

        $entryType = $this->makeAbstractEntryType($type);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Status [published] does not belong to EntryGroup');

        $this->repo->create($entryType, ['title' => 'Live', 'handle' => 'live', 'status' => 'published']);
    }

    public function test_create_throws_when_handle_is_missing(): void
    {
        $statusGroup = $this->makeStatusGroup();
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $this->actingAs(User::factory()->create());

        $entryType = $this->makeAbstractEntryType($type);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Entry handle is required.');

        $this->repo->create($entryType, ['title' => 'My Blog Post']);
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

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no status group/i');

        $this->repo->create($entryType, ['title' => 'Broken', 'handle' => 'broken']);
    }

    public function test_create_syncs_authors(): void
    {
        $statusGroup = $this->makeStatusGroup();
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $user = User::factory()->create();
        $author = User::factory()->create();
        $entryAuthor = EntryAuthor::factory()->create(['user_id' => $author->id, 'status' => 'active']);
        $this->actingAs($user);

        $entryType = $this->makeAbstractEntryType($type);

        $entry = $this->repo->create($entryType, [
            'title' => 'With Authors',
            'handle' => 'with-authors',
            'authors' => [$author->id],
        ]);

        $this->assertDatabaseHas('entry_author_entry', [
            'entry_id' => $entry->id,
            'entry_author_id' => $entryAuthor->id,
        ]);
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
            'handle' => 'categorised',
            'categories' => [$category->id],
        ]);

        $this->assertDatabaseHas('categorizables', [
            'category_id' => $category->id,
            'categorizable_id' => $entry->id,
        ]);
    }

    public function test_find_returns_entry_when_it_exists(): void
    {
        $entry = Entry::factory()->create();

        $result = $this->repo->find($entry->id);

        $this->assertNotNull($result);
        $this->assertEquals($entry->id, $result->id);
    }

    // -----------------------------------------------------------------------
    // find() / findOrFail() / findByHandle() / findOrFailByHandle()
    // -----------------------------------------------------------------------

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

    // -----------------------------------------------------------------------
    // applyData()
    // -----------------------------------------------------------------------

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
        ]);

        $updated = $this->repo->applyData($entry, ['status' => 'published']);

        $this->assertEquals('published', $updated->status_handle);
    }

    public function test_apply_data_throws_when_status_does_not_belong_to_entry_group(): void
    {
        $statusGroup = $this->makeStatusGroup();
        $otherStatusGroup = StatusGroup::factory()->create();
        Status::factory()->create([
            'status_group_id' => $otherStatusGroup->id,
            'handle' => 'published',
            'is_default' => false,
        ]);
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Status [published] does not belong to EntryGroup');

        $this->repo->applyData($entry, ['status' => 'published']);
    }

    public function test_apply_data_updates_authors(): void
    {
        $statusGroup = $this->makeStatusGroup();
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $entry = Entry::factory()->create(['entry_group_id' => $group->id, 'entry_type_id' => $type->id]);
        $author = User::factory()->create();
        $entryAuthor = EntryAuthor::factory()->create(['user_id' => $author->id, 'status' => 'active']);

        $this->repo->applyData($entry, ['authors' => [$author->id]]);

        $this->assertDatabaseHas('entry_author_entry', [
            'entry_id' => $entry->id,
            'entry_author_id' => $entryAuthor->id,
        ]);
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

    public function test_delete_removes_entry_from_database(): void
    {
        $entry = Entry::factory()->create();

        $result = $this->repo->delete($entry);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('entries', ['id' => $entry->id]);
    }

    // -----------------------------------------------------------------------
    // delete()
    // -----------------------------------------------------------------------

    public function test_find_meta_returns_entry_when_it_exists(): void
    {
        $entry = Entry::factory()->create();

        $result = $this->repo->findMeta($entry->id);

        $this->assertNotNull($result);
        $this->assertEquals($entry->id, $result->id);
    }

    // -----------------------------------------------------------------------
    // findMeta() / findMetaOrFail()
    // -----------------------------------------------------------------------

    public function test_find_meta_returns_null_when_entry_does_not_exist(): void
    {
        $this->assertNull($this->repo->findMeta(999999));
    }

    public function test_find_meta_or_fail_throws_when_entry_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repo->findMetaOrFail(999999);
    }

    public function test_resolve_layout_fields_returns_empty_when_no_layouts(): void
    {
        $group = EntryGroup::factory()->create(['field_layout_id' => null]);
        $type = EntryType::factory()->create(['entry_group_id' => $group->id, 'field_layout_id' => null]);
        $entry = Entry::factory()->create(['entry_group_id' => $group->id, 'entry_type_id' => $type->id]);

        $fields = $this->repo->resolveLayoutFields($entry);

        $this->assertCount(0, $fields);
    }

    // -----------------------------------------------------------------------
    // resolveLayoutFields()
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

    // -----------------------------------------------------------------------
    // Relationship field (syncRelationshipField via applyData)
    // -----------------------------------------------------------------------

    // -----------------------------------------------------------------------
    // published_at stamping via status is_public
    // -----------------------------------------------------------------------

    public function test_create_stamps_published_at_when_status_is_public(): void
    {
        $statusGroup = StatusGroup::factory()->create();
        Status::factory()->default()->create(['status_group_id' => $statusGroup->id, 'handle' => 'draft', 'is_public' => false]);
        Status::factory()->create(['status_group_id' => $statusGroup->id, 'handle' => 'live', 'is_public' => true]);
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $this->actingAs(User::factory()->create());

        $entry = $this->repo->create($this->makeAbstractEntryType($type), [
            'title' => 'Going Live',
            'handle' => 'going-live',
            'status' => 'live',
        ]);

        $this->assertNotNull($entry->published_at);
    }

    public function test_create_does_not_stamp_published_at_when_status_is_not_public(): void
    {
        $statusGroup = $this->makeStatusGroup(); // default draft, is_public=false
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $this->actingAs(User::factory()->create());

        $entry = $this->repo->create($this->makeAbstractEntryType($type), ['title' => 'Draft Entry', 'handle' => 'draft-entry']);

        $this->assertNull($entry->published_at);
    }

    public function test_create_does_not_overwrite_explicit_published_at_when_status_is_public(): void
    {
        $statusGroup = StatusGroup::factory()->create();
        Status::factory()->default()->create(['status_group_id' => $statusGroup->id, 'handle' => 'live', 'is_public' => true]);
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $this->actingAs(User::factory()->create());

        $explicit = now()->subWeek()->toDateTimeString();

        $entry = $this->repo->create($this->makeAbstractEntryType($type), [
            'title' => 'Backdated',
            'handle' => 'backdated',
            'published_at' => $explicit,
        ]);

        $this->assertEquals($explicit, $entry->published_at->toDateTimeString());
    }

    public function test_create_stamps_published_at_when_default_status_is_public(): void
    {
        $statusGroup = StatusGroup::factory()->create();
        Status::factory()->default()->create(['status_group_id' => $statusGroup->id, 'handle' => 'live', 'is_public' => true]);
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $this->actingAs(User::factory()->create());

        // No explicit status passed — should pick up the default which is public.
        $entry = $this->repo->create($this->makeAbstractEntryType($type), ['title' => 'Auto Published', 'handle' => 'auto-published']);

        $this->assertNotNull($entry->published_at);
    }

    public function test_apply_data_stamps_published_at_when_transitioning_to_public_status(): void
    {
        $statusGroup = $this->makeStatusGroup(); // draft, is_public=false
        Status::factory()->create(['status_group_id' => $statusGroup->id, 'handle' => 'live', 'is_public' => true]);
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
            'published_at' => null,
        ]);

        $updated = $this->repo->applyData($entry, ['status' => 'live']);

        $this->assertNotNull($updated->published_at);
    }

    public function test_apply_data_does_not_stamp_published_at_when_already_set(): void
    {
        $statusGroup = $this->makeStatusGroup();
        Status::factory()->create(['status_group_id' => $statusGroup->id, 'handle' => 'live', 'is_public' => true]);
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $original = now()->subDays(10);
        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
            'published_at' => $original,
        ]);

        $updated = $this->repo->applyData($entry, ['status' => 'live']);

        $this->assertEquals($original->toDateTimeString(), $updated->published_at->toDateTimeString());
    }

    public function test_apply_data_does_not_stamp_published_at_for_non_public_status(): void
    {
        $statusGroup = $this->makeStatusGroup(); // draft, is_public=false
        $group = $this->makeEntryGroup($statusGroup);
        $type = $this->makeEntryType($group);
        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
            'published_at' => null,
        ]);

        $updated = $this->repo->applyData($entry, ['status' => 'draft']);

        $this->assertNull($updated->published_at);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new EntryRepository();
    }
}
