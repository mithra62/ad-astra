<?php

namespace Tests\Unit\Services;

use AdAstra\Models\Entry;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryTree;
use AdAstra\Models\EntryType;
use AdAstra\Models\Status;
use AdAstra\Models\User;
use AdAstra\Services\EntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Covers the Entry Tree data path through EntryService::create() and
 * update()/syncTreeNode():
 *
 *   - parent placement via the `parent_entry_id` data key (regression for the
 *     request/service key mismatch that silently rooted every node)
 *   - redirect_url / redirect_status persistence on create and update
 *   - append-at-end sort order when a node moves to a new parent
 *   - last-write-wins `is_home` promotion/demotion
 */
class EntryServiceTreeSyncTest extends TestCase
{
    use RefreshDatabase;

    private EntryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EntryService::class);

        // The create() path stamps created_by_user_id from the authenticated user.
        $this->actingAs(User::factory()->create());
    }

    // -------------------------------------------------------------------------
    // Fixtures
    // -------------------------------------------------------------------------

    private function makeTreeGroup(): EntryGroup
    {
        $group = EntryGroup::factory()->create();

        // Entry creation falls back to the group's default status (EntryRepository).
        Status::factory()->default()->create([
            'status_group_id' => $group->status_group_id,
            'handle' => 'draft',
        ]);

        return $group;
    }

    private function makeTreeType(EntryGroup $group): EntryType
    {
        return EntryType::factory()->create([
            'entry_group_id' => $group->id,
            'handle' => 'page-' . fake()->unique()->numberBetween(1, 999999),
            'has_entry_tree' => true,
        ]);
    }

    /**
     * Create an entry (via factory) with an Entry Tree node already attached.
     */
    private function makeTreeEntry(
        EntryGroup $group,
        string     $handle,
        ?EntryTree $parent = null,
        bool       $isHome = false,
    ): Entry {
        $entry = Entry::factory()
            ->for($group)
            ->for($this->makeTreeType($group))
            ->create(['handle' => $handle]);

        $this->service->createTreeNode($entry, $handle, $parent, null, $isHome);

        return $entry;
    }

    private function nodeFor(Entry $entry): EntryTree
    {
        return EntryTree::where('entry_id', $entry->id)->firstOrFail();
    }

    // -------------------------------------------------------------------------
    // create() — parent placement
    // -------------------------------------------------------------------------

    public function test_create_honors_parent_entry_id_key(): void
    {
        $group = $this->makeTreeGroup();
        $parent = $this->makeTreeEntry($group, 'about');
        $type = $this->makeTreeType($group);

        $entry = $this->service->create($type->handle, [
            'title' => 'Team',
            'handle' => 'team',
            'parent_entry_id' => $parent->id,
        ]);

        $node = $this->nodeFor($entry);
        $this->assertSame($this->nodeFor($parent)->id, $node->parent_id);
        $this->assertSame('about/team', $node->uri);
        $this->assertSame(1, $node->depth);
    }

    // -------------------------------------------------------------------------
    // create() — redirect persistence
    // -------------------------------------------------------------------------

    public function test_create_persists_redirect_url_and_defaults_status_to_302_when_null(): void
    {
        $group = $this->makeTreeGroup();
        $type = $this->makeTreeType($group);

        $entry = $this->service->create($type->handle, [
            'title' => 'Landing',
            'handle' => 'landing',
            'redirect_url' => 'https://example.com/new-location',
        ]);

        $node = $this->nodeFor($entry);
        $this->assertSame('https://example.com/new-location', $node->redirect_url);
        $this->assertSame(302, (int)$node->redirect_status);
    }

    public function test_create_persists_explicit_redirect_status(): void
    {
        $group = $this->makeTreeGroup();
        $type = $this->makeTreeType($group);

        $entry = $this->service->create($type->handle, [
            'title' => 'Landing',
            'handle' => 'landing',
            'redirect_url' => 'https://example.com/moved',
            'redirect_status' => 301,
        ]);

        $this->assertSame(301, (int)$this->nodeFor($entry)->redirect_status);
    }

    // -------------------------------------------------------------------------
    // update() — parent moves
    // -------------------------------------------------------------------------

    public function test_update_moving_node_appends_at_end_of_new_siblings(): void
    {
        $group = $this->makeTreeGroup();
        $sectionA = $this->makeTreeEntry($group, 'section-a');
        $sectionB = $this->makeTreeEntry($group, 'section-b');
        $this->makeTreeEntry($group, 'b-one', $this->nodeFor($sectionB));
        $this->makeTreeEntry($group, 'b-two', $this->nodeFor($sectionB));
        $moved = $this->makeTreeEntry($group, 'moved', $this->nodeFor($sectionA));

        $this->service->update($moved, ['parent_entry_id' => $sectionB->id]);

        $node = $this->nodeFor($moved);
        $this->assertSame($this->nodeFor($sectionB)->id, $node->parent_id);
        $this->assertSame(3, $node->sort_order);
        $this->assertSame('section-b/moved', $node->uri);
    }

    public function test_update_moving_node_to_root_via_null_parent_entry_id(): void
    {
        $group = $this->makeTreeGroup();
        $section = $this->makeTreeEntry($group, 'section');
        $child = $this->makeTreeEntry($group, 'child', $this->nodeFor($section));

        $this->service->update($child, ['parent_entry_id' => null]);

        $node = $this->nodeFor($child);
        $this->assertNull($node->parent_id);
        $this->assertSame('child', $node->uri);
        $this->assertSame(0, $node->depth);
    }

    // -------------------------------------------------------------------------
    // update() — redirect sync
    // -------------------------------------------------------------------------

    public function test_update_syncs_redirect_pair_only_when_keys_present(): void
    {
        $group = $this->makeTreeGroup();
        $type = $this->makeTreeType($group);
        $entry = $this->service->create($type->handle, [
            'title' => 'Landing',
            'handle' => 'landing',
            'redirect_url' => 'https://example.com/original',
            'redirect_status' => 301,
        ]);

        // Keys absent — redirect pair must be untouched.
        $this->service->update($entry, ['title' => 'Landing Renamed']);

        $node = $this->nodeFor($entry);
        $this->assertSame('https://example.com/original', $node->redirect_url);
        $this->assertSame(301, (int)$node->redirect_status);

        // Keys present — values update.
        $this->service->update($entry->fresh(), [
            'redirect_url' => 'https://example.com/changed',
            'redirect_status' => 308,
        ]);

        $node = $this->nodeFor($entry);
        $this->assertSame('https://example.com/changed', $node->redirect_url);
        $this->assertSame(308, (int)$node->redirect_status);
    }

    public function test_update_null_redirect_url_clears_and_null_status_resets_302(): void
    {
        $group = $this->makeTreeGroup();
        $type = $this->makeTreeType($group);
        $entry = $this->service->create($type->handle, [
            'title' => 'Landing',
            'handle' => 'landing',
            'redirect_url' => 'https://example.com/original',
            'redirect_status' => 301,
        ]);

        $this->service->update($entry, [
            'redirect_url' => null,
            'redirect_status' => null,
        ]);

        $node = $this->nodeFor($entry);
        $this->assertNull($node->redirect_url);
        $this->assertSame(302, (int)$node->redirect_status);
    }

    // -------------------------------------------------------------------------
    // update() — is_home promotion / demotion
    // -------------------------------------------------------------------------

    public function test_update_promotes_node_to_home_sets_handle_and_uri(): void
    {
        $group = $this->makeTreeGroup();
        $about = $this->makeTreeEntry($group, 'about');
        $this->makeTreeEntry($group, 'team', $this->nodeFor($about));

        $this->service->update($about, ['is_home' => true]);

        $node = $this->nodeFor($about);
        $this->assertTrue((bool)$node->is_home);
        $this->assertSame('home', $node->handle);
        $this->assertSame('/', $node->uri);

        // Home nodes contribute no URI segment — child drops the parent prefix.
        $child = EntryTree::where('parent_id', $node->id)->firstOrFail();
        $this->assertSame('team', $child->uri);
    }

    public function test_update_promote_auto_demotes_existing_home(): void
    {
        $group = $this->makeTreeGroup();
        $oldHome = $this->makeTreeEntry($group, 'old-home', null, true);
        $fresh = $this->makeTreeEntry($group, 'fresh');

        $this->service->update($fresh, ['is_home' => true]);

        $oldNode = $this->nodeFor($oldHome);
        $this->assertFalse((bool)$oldNode->is_home);
        $this->assertSame('old-home', $oldNode->handle);
        $this->assertSame('old-home', $oldNode->uri);

        $newNode = $this->nodeFor($fresh);
        $this->assertTrue((bool)$newNode->is_home);
        $this->assertSame('home', $newNode->handle);
        $this->assertSame('/', $newNode->uri);
    }

    public function test_create_with_is_home_auto_demotes_existing_home(): void
    {
        $group = $this->makeTreeGroup();
        $oldHome = $this->makeTreeEntry($group, 'old-home', null, true);
        $type = $this->makeTreeType($group);

        $entry = $this->service->create($type->handle, [
            'title' => 'New Home',
            'handle' => 'new-home',
            'is_home' => true,
        ]);

        $oldNode = $this->nodeFor($oldHome);
        $this->assertFalse((bool)$oldNode->is_home);
        $this->assertSame('old-home', $oldNode->handle);

        $newNode = $this->nodeFor($entry);
        $this->assertTrue((bool)$newNode->is_home);
        $this->assertSame('/', $newNode->uri);
    }

    public function test_update_promote_throws_validation_exception_when_node_not_root(): void
    {
        $group = $this->makeTreeGroup();
        $section = $this->makeTreeEntry($group, 'section');
        $child = $this->makeTreeEntry($group, 'child', $this->nodeFor($section));

        $this->expectException(ValidationException::class);

        $this->service->update($child, ['is_home' => true]);
    }

    public function test_update_promote_with_simultaneous_move_to_root_succeeds(): void
    {
        $group = $this->makeTreeGroup();
        $section = $this->makeTreeEntry($group, 'section');
        $child = $this->makeTreeEntry($group, 'child', $this->nodeFor($section));

        $this->service->update($child, ['is_home' => true, 'parent_entry_id' => null]);

        $node = $this->nodeFor($child);
        $this->assertTrue((bool)$node->is_home);
        $this->assertNull($node->parent_id);
        $this->assertSame('home', $node->handle);
        $this->assertSame('/', $node->uri);
    }

    public function test_update_demotes_home_restores_entry_handle_and_rebuilds_uris(): void
    {
        $group = $this->makeTreeGroup();
        $home = $this->makeTreeEntry($group, 'welcome', null, true);
        $this->makeTreeEntry($group, 'team', $this->nodeFor($home));

        $this->service->update($home, ['is_home' => false]);

        $node = $this->nodeFor($home);
        $this->assertFalse((bool)$node->is_home);
        $this->assertSame('welcome', $node->handle);
        $this->assertSame('welcome', $node->uri);

        // Demoted nodes contribute a URI segment again — child regains the prefix.
        $child = EntryTree::where('parent_id', $node->id)->firstOrFail();
        $this->assertSame('welcome/team', $child->uri);
    }

    public function test_demoted_home_handle_collision_appends_node_id(): void
    {
        // The Entry Tree is site-wide, so a root node in another group can own
        // the slug the demoted home would restore to.
        $groupA = $this->makeTreeGroup();
        $groupB = $this->makeTreeGroup();
        $this->makeTreeEntry($groupA, 'about');
        $oldHome = $this->makeTreeEntry($groupB, 'about', null, true);
        $oldHomeNode = $this->nodeFor($oldHome);

        $fresh = $this->makeTreeEntry($groupA, 'fresh');
        $this->service->update($fresh, ['is_home' => true]);

        $demoted = $this->nodeFor($oldHome);
        $this->assertFalse((bool)$demoted->is_home);
        $this->assertSame('about-' . $oldHomeNode->id, $demoted->handle);
    }
}
