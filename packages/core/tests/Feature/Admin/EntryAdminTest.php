<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\Entry;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryTree;
use AdAstra\Models\EntryType;
use AdAstra\Models\Status;
use AdAstra\Models\User;
use AdAstra\Services\EntryTreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\Entry controller (entry CRUD within a group).
 */
class EntryAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    private function group(): EntryGroup
    {
        $group = EntryGroup::factory()->create();
        Status::factory()->default()->create([
            'status_group_id' => $group->status_group_id,
            'handle' => 'draft',
        ]);

        return $group;
    }

    private function typeFor(EntryGroup $group, string $handle = 'article'): EntryType
    {
        return EntryType::factory()->create(['entry_group_id' => $group->id, 'handle' => $handle]);
    }

    private function entryIn(EntryGroup $group, array $attributes = []): Entry
    {
        $type = $this->typeFor($group, 'type-' . fake()->unique()->numberBetween(1, 999999));

        return Entry::factory()->create(array_merge([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
        ], $attributes));
    }

    // -------------------------------------------------------------------------
    // Auth boundaries
    // -------------------------------------------------------------------------

    public function test_create_redirects_guests_to_login(): void
    {
        $group = $this->group();

        $this->get(route('entries.create', ['group_id' => $group->id]))
            ->assertRedirect(route('login'));
    }

    public function test_create_forbids_non_admin_user(): void
    {
        $group = $this->group();

        $this->actingAs(User::factory()->create())
            ->get(route('entries.create', ['group_id' => $group->id]))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // create (render)
    // -------------------------------------------------------------------------

    public function test_create_renders_when_group_has_a_type(): void
    {
        $group = $this->group();
        $this->typeFor($group);

        $this->actingAs($this->admin)
            ->get(route('entries.create', ['group_id' => $group->id]))
            ->assertOk();
    }

    public function test_create_redirects_with_failure_when_group_has_no_types(): void
    {
        $group = $this->group();

        $this->actingAs($this->admin)
            ->get(route('entries.create', ['group_id' => $group->id]))
            ->assertRedirect(route('entries.groups.show', $group->id))
            ->assertSessionHas('failure');
    }

    public function test_create_returns_404_for_missing_group(): void
    {
        $this->actingAs($this->admin)
            ->get(route('entries.create', ['group_id' => 999999]))
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_entry_and_redirects_to_group(): void
    {
        $group = $this->group();
        $this->typeFor($group);

        $this->actingAs($this->admin)
            ->post(route('entries.store', ['group_id' => $group->id]), [
                'type_handle' => 'article',
                'title' => 'Hello World',
                'handle' => 'hello-world',
            ])
            ->assertRedirect(route('entries.groups.show', $group->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('entries', [
            'entry_group_id' => $group->id,
            'handle' => 'hello-world',
        ]);
    }

    public function test_store_validation_failure(): void
    {
        $group = $this->group();
        $this->typeFor($group);

        $this->actingAs($this->admin)
            ->post(route('entries.store', ['group_id' => $group->id]), ['type_handle' => 'article'])
            ->assertSessionHasErrors(['title', 'handle']);
    }

    // -------------------------------------------------------------------------
    // edit / confirm (render)
    // -------------------------------------------------------------------------

    public function test_edit_renders(): void
    {
        $group = $this->group();
        $entry = $this->entryIn($group);

        $this->actingAs($this->admin)->get(route('entries.edit', $entry->id))->assertOk();
    }

    public function test_edit_renders_assign_authors_field_with_eligible_authors(): void
    {
        $group = $this->group();
        $entry = $this->entryIn($group);
        // The taxonomy tab (which contains Assign Authors) only renders when
        // the group has at least one category group (see edit.twig:140) —
        // unrelated to authors, but required to reach the tab at all.
        $group->categoryGroups()->attach(\AdAstra\Models\Category\Group::factory()->create());
        $author = \AdAstra\Models\EntryAuthor::factory()->create([
            'status' => 'active',
            'display_name' => 'Temp Verification Author',
        ]);
        $entry->authors()->attach($author->id);

        $response = $this->actingAs($this->admin)->get(route('entries.edit', $entry->id));

        $response->assertOk();
        $response->assertSee('data-choices', false);
        $response->assertSee('Temp Verification Author', false);
        $response->assertSee('authors[]', false);
    }

    public function test_edit_returns_404_for_missing_entry(): void
    {
        $this->actingAs($this->admin)->get(route('entries.edit', 999999))->assertNotFound();
    }

    public function test_confirm_renders(): void
    {
        $group = $this->group();
        $entry = $this->entryIn($group);

        $this->actingAs($this->admin)->get(route('entries.confirm', $entry->id))->assertOk();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_entry_and_redirects(): void
    {
        $group = $this->group();
        $entry = $this->entryIn($group, ['title' => 'Old', 'handle' => 'old']);

        $this->actingAs($this->admin)
            ->put(route('entries.update', $entry->id), ['title' => 'New Title', 'handle' => 'old'])
            ->assertRedirect(route('entries.edit', $entry->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('entries', ['id' => $entry->id, 'title' => 'New Title']);
    }

    public function test_update_returns_404_for_missing_entry(): void
    {
        $this->actingAs($this->admin)
            ->put(route('entries.update', 999999), ['title' => 'Nope', 'handle' => 'nope'])
            ->assertNotFound();
    }

    public function test_update_with_parent_and_redirect_persists_tree_changes(): void
    {
        $group = $this->group();
        $service = app(EntryTreeService::class);

        $treeType = fn () => EntryType::factory()->create([
            'entry_group_id' => $group->id,
            'handle' => 'page-' . fake()->unique()->numberBetween(1, 999999),
            'has_entry_tree' => true,
        ]);

        $parent = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $treeType()->id,
            'handle' => 'about',
        ]);
        $service->createTreeNode($parent, 'about');

        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $treeType()->id,
            'handle' => 'contact',
            'title' => 'Contact',
        ]);
        $service->createTreeNode($entry, 'contact');

        // Mirrors what admin.entries._hierarchy actually submits.
        $this->actingAs($this->admin)
            ->put(route('entries.update', $entry->id), [
                'title' => 'Contact',
                'handle' => 'contact',
                'parent_entry_id' => $parent->id,
                'redirect_url' => 'https://example.com/contact-us',
                'redirect_status' => 301,
                'template' => 'pages/contact',
            ])
            ->assertRedirect(route('entries.edit', $entry->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('entry_trees', [
            'entry_id' => $entry->id,
            'parent_id' => EntryTree::where('entry_id', $parent->id)->value('id'),
            'uri' => 'about/contact',
            'redirect_url' => 'https://example.com/contact-us',
            'redirect_status' => 301,
            'template' => 'pages/contact',
        ]);
    }

    public function test_edit_prefills_the_parent_picker_with_the_current_tree_parent(): void
    {
        $group = $this->group();
        $service = app(EntryTreeService::class);

        $treeType = fn () => EntryType::factory()->create([
            'entry_group_id' => $group->id,
            'handle' => 'page-' . fake()->unique()->numberBetween(1, 999999),
            'has_entry_tree' => true,
        ]);

        $parent = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $treeType()->id,
            'title' => 'About Section',
            'handle' => 'about',
        ]);
        $service->createTreeNode($parent, 'about');

        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $treeType()->id,
            'handle' => 'contact',
        ]);
        $service->createTreeNode($entry, 'contact', $parent->entryTree);

        $response = $this->actingAs($this->admin)
            ->get(route('entries.edit', $entry->id))
            ->assertOk();

        $response->assertSee('data-parent-picker', false);
        $response->assertSee('value="' . $parent->id . '" selected', false);
        $response->assertSee('About Section', false);
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_entry_and_redirects(): void
    {
        $group = $this->group();
        $entry = $this->entryIn($group);

        $this->actingAs($this->admin)
            ->delete(route('entries.destroy', $entry->id), ['confirm_removal' => 1])
            ->assertRedirect(route('entries.groups.show', $group->id))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('entries', ['id' => $entry->id]);
    }

    public function test_destroy_requires_confirmation(): void
    {
        $group = $this->group();
        $entry = $this->entryIn($group);

        $this->actingAs($this->admin)
            ->delete(route('entries.destroy', $entry->id), [])
            ->assertSessionHasErrors('confirm_removal');

        $this->assertDatabaseHas('entries', ['id' => $entry->id]);
    }
}
