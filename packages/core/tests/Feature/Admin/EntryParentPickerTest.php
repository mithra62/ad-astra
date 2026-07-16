<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\Entry;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryTree;
use AdAstra\Models\EntryType;
use AdAstra\Models\Role;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\Entry::entry_picker JSON endpoint that
 * backs the entry Hierarchy tab's ajax parent picker.
 */
class EntryParentPickerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_redirects_or_rejects(): void
    {
        $group = EntryGroup::factory()->create();

        $response = $this->getJson(route('entries.parent_picker.index', ['entry_group_id' => $group->id]));

        // Admin routes use the `auth` middleware; an unauthenticated JSON
        // request returns 401, an unauthenticated browser request redirects.
        $this->assertContains($response->status(), [302, 401, 403]);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function test_request_without_entry_group_id_fails_validation(): void
    {
        $user = $this->makeSuperAdmin();

        $this->actingAs($user)
            ->getJson(route('entries.parent_picker.index'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['entry_group_id']);
    }

    // -------------------------------------------------------------------------
    // Tree requirement
    // -------------------------------------------------------------------------

    public function test_returns_only_entries_with_tree_nodes(): void
    {
        $user = $this->makeSuperAdmin();
        $group = EntryGroup::factory()->create();

        $withTree = $this->treeEntryIn($group, 'With Tree');
        $this->entryIn($group, 'Without Tree');

        $response = $this->actingAs($user)
            ->getJson(route('entries.parent_picker.index', ['entry_group_id' => $group->id]))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total', 'current_page', 'last_page', 'per_page']]);

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($withTree->id, $response->json('data.0.id'));
    }

    // -------------------------------------------------------------------------
    // Group scoping
    // -------------------------------------------------------------------------

    public function test_results_are_scoped_to_the_requested_entry_group(): void
    {
        $user = $this->makeSuperAdmin();
        $groupA = EntryGroup::factory()->create();
        $groupB = EntryGroup::factory()->create();

        $inA = $this->treeEntryIn($groupA, 'Group A Page');
        $this->treeEntryIn($groupB, 'Group B Page');

        $response = $this->actingAs($user)
            ->getJson(route('entries.parent_picker.index', ['entry_group_id' => $groupA->id]))
            ->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($inA->id, $response->json('data.0.id'));
    }

    // -------------------------------------------------------------------------
    // Exclusion
    // -------------------------------------------------------------------------

    public function test_exclude_omits_the_given_entry(): void
    {
        $user = $this->makeSuperAdmin();
        $group = EntryGroup::factory()->create();

        $self = $this->treeEntryIn($group, 'The Entry Being Edited');
        $other = $this->treeEntryIn($group, 'A Candidate Parent');

        $response = $this->actingAs($user)
            ->getJson(route('entries.parent_picker.index', [
                'entry_group_id' => $group->id,
                'exclude' => $self->id,
            ]))
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($other->id, $ids);
        $this->assertNotContains($self->id, $ids);
    }

    // -------------------------------------------------------------------------
    // Search filter
    // -------------------------------------------------------------------------

    public function test_q_param_filters_by_title(): void
    {
        $user = $this->makeSuperAdmin();
        $group = EntryGroup::factory()->create();

        $this->treeEntryIn($group, 'About Us');
        $this->treeEntryIn($group, 'Contact');
        $this->treeEntryIn($group, 'Products');

        $response = $this->actingAs($user)
            ->getJson(route('entries.parent_picker.index', ['entry_group_id' => $group->id, 'q' => 'abo']))
            ->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('About Us', $response->json('data.0.title'));
    }

    public function test_q_param_escapes_like_wildcards(): void
    {
        $user = $this->makeSuperAdmin();
        $group = EntryGroup::factory()->create();

        $this->treeEntryIn($group, 'page_one');
        $this->treeEntryIn($group, 'pageXone');

        // Underscore is a SQL LIKE wildcard; with escaping, only the literal "_one" should match.
        $response = $this->actingAs($user)
            ->getJson(route('entries.parent_picker.index', ['entry_group_id' => $group->id, 'q' => '_one']))
            ->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('page_one', $response->json('data.0.title'));
    }

    // -------------------------------------------------------------------------
    // Pagination + response shape
    // -------------------------------------------------------------------------

    public function test_pagination_respects_per_page(): void
    {
        $user = $this->makeSuperAdmin();
        $group = EntryGroup::factory()->create();

        foreach (range(1, 5) as $i) {
            $this->treeEntryIn($group, "Page {$i}");
        }

        $response = $this->actingAs($user)
            ->getJson(route('entries.parent_picker.index', ['entry_group_id' => $group->id, 'per_page' => 2]))
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame(5, $response->json('meta.total'));
        $this->assertSame(3, $response->json('meta.last_page'));
    }

    public function test_response_includes_expected_fields_per_item(): void
    {
        $user = $this->makeSuperAdmin();
        $group = EntryGroup::factory()->create();

        $entry = $this->entryIn($group, 'Team');
        EntryTree::factory()->create(['entry_id' => $entry->id, 'uri' => 'about/team']);

        $response = $this->actingAs($user)
            ->getJson(route('entries.parent_picker.index', ['entry_group_id' => $group->id]))
            ->assertOk();

        $item = $response->json('data.0');
        $this->assertSame($entry->id, $item['id']);
        $this->assertSame('Team', $item['title']);
        $this->assertSame('about/team', $item['uri']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    private function entryIn(EntryGroup $group, string $title): Entry
    {
        $type = EntryType::factory()->create([
            'entry_group_id' => $group->id,
            'handle' => 'type-' . fake()->unique()->numberBetween(1, 999999),
        ]);

        return Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
            'title' => $title,
        ]);
    }

    private function treeEntryIn(EntryGroup $group, string $title): Entry
    {
        $entry = $this->entryIn($group, $title);
        EntryTree::factory()->create(['entry_id' => $entry->id]);

        return $entry;
    }
}
