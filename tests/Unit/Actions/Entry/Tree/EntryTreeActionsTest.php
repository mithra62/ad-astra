<?php

namespace Tests\Unit\Actions\Entry\Tree;

use App\Actions\Entry\Tree\CreateEntryTreeNode;
use App\Actions\Entry\Tree\MoveEntryTreeNode;
use App\Actions\Entry\Tree\RebuildEntryTreeUri;
use App\Models\Entry;
use App\Models\EntryGroup;
use App\Models\EntryTree;
use App\Models\EntryType;
use App\Services\EntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class EntryTreeActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_entry_tree_node_builds_handle_uri_and_depth(): void
    {
        $service = app(EntryService::class);
        $parentEntry = $this->makeTreeEntry();
        $childEntry = $this->makeTreeEntry();

        $parent = $service->createTreeNode($parentEntry, 'About Us');
        $child = $service->createTreeNode($childEntry, 'Leadership Team', $parent);

        $this->assertSame('about-us', $parent->handle);
        $this->assertSame('about-us', $parent->uri);
        $this->assertSame(0, $parent->depth);
        $this->assertSame('leadership-team', $child->handle);
        $this->assertSame('about-us/leadership-team', $child->uri);
        $this->assertSame(1, $child->depth);
        $this->assertSame($childEntry->entryType->id, $child->entry->entryType->id);
    }

    protected function makeTreeEntry(array $entryOverrides = [], array $typeOverrides = []): Entry
    {
        $group = EntryGroup::factory()->create();
        $type = EntryType::factory()->for($group)->create(array_merge([
            'has_entry_tree' => true,
            'default_template' => 'entries.page',
        ], $typeOverrides));

        return Entry::factory()
            ->for($group)
            ->for($type)
            ->create($entryOverrides);
    }

    public function test_create_entry_tree_node_rejects_invalid_handles(): void
    {
        $service = app(EntryService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Entry Tree handles must contain at least one URL-safe character.');

        $service->createTreeNode($this->makeTreeEntry(), '!!!');
    }

    public function test_create_entry_tree_node_enforces_home_node_invariants(): void
    {
        $service = app(EntryService::class);
        $parent = $service->createTreeNode($this->makeTreeEntry(), 'Root');

        $home = $service->createTreeNode($this->makeTreeEntry(), 'Home', null, null, true);

        $this->assertSame('/', $home->uri);

        try {
            $service->createTreeNode($this->makeTreeEntry(), 'Nested Home', $parent, null, true);
            $this->fail('Expected nested home creation to fail.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('The Entry Tree home node must be a root node.', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only one Entry Tree home node may exist.');

        $service->createTreeNode($this->makeTreeEntry(), 'Second Home', null, null, true);
    }

    public function test_create_entry_tree_node_prevents_duplicate_root_handles(): void
    {
        $service = app(EntryService::class);

        $service->createTreeNode($this->makeTreeEntry(), 'About');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An Entry Tree node with handle [about] already exists at this level.');

        $service->createTreeNode($this->makeTreeEntry(), 'About');
    }

    public function test_move_entry_tree_node_rebuilds_subtree_and_rebalances_sort_order(): void
    {
        $service = app(EntryService::class);

        $rootA = $service->createTreeNode($this->makeTreeEntry(), 'Section A');
        $rootB = $service->createTreeNode($this->makeTreeEntry(), 'Section B');
        $firstChild = $service->createTreeNode($this->makeTreeEntry(), 'First Child', $rootA);
        $movedChild = $service->createTreeNode($this->makeTreeEntry(), 'Moved Child', $rootA);
        $grandchild = $service->createTreeNode($this->makeTreeEntry(), 'Grand Child', $movedChild);

        $moved = $service->moveTreeNode($movedChild, $rootB, 1);

        $this->assertSame($rootB->id, $moved->parent_id);
        $this->assertSame(1, $moved->sort_order);
        $this->assertSame('section-b/moved-child', $moved->uri);

        $grandchild->refresh();
        $this->assertSame(2, $grandchild->depth);
        $this->assertSame('section-b/moved-child/grand-child', $grandchild->uri);

        $firstChild->refresh();
        $this->assertSame(1, $firstChild->sort_order);
    }

    public function test_move_entry_tree_node_prevents_duplicate_handles_in_target_parent(): void
    {
        $service = app(EntryService::class);

        $rootA = $service->createTreeNode($this->makeTreeEntry(), 'Section A');
        $rootB = $service->createTreeNode($this->makeTreeEntry(), 'Section B');
        $candidate = $service->createTreeNode($this->makeTreeEntry(), 'Child', $rootA);
        $service->createTreeNode($this->makeTreeEntry(), 'Child', $rootB);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An Entry Tree node with handle [child] already exists at this level.');

        $service->moveTreeNode($candidate, $rootB, 1);
    }

    public function test_rebuild_entry_tree_uri_rejects_non_root_home_nodes(): void
    {
        $parent = EntryTree::create([
            'entry_id' => $this->makeTreeEntry()->id,
            'parent_id' => null,
            'handle' => 'parent',
            'uri' => 'parent',
            'depth' => 0,
            'sort_order' => 1,
            'template' => null,
            'is_home' => false,
        ]);

        $invalidHome = EntryTree::create([
            'entry_id' => $this->makeTreeEntry()->id,
            'parent_id' => $parent->id,
            'handle' => 'home',
            'uri' => '/',
            'depth' => 1,
            'sort_order' => 1,
            'template' => null,
            'is_home' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Entry Tree home node must remain at the root.');

        app(EntryService::class)->rebuildTreeUri($invalidHome);
    }

    // -------------------------------------------------------------------------
    // CreateEntryTreeNode action wrapper — delegation
    // -------------------------------------------------------------------------

    public function test_create_tree_node_action_delegates_to_service(): void
    {
        $entry = $this->makeTreeEntry();
        $node = EntryTree::factory()->create(['entry_id' => $entry->id]);
        $service = $this->mock(EntryService::class);
        $service->shouldReceive('createTreeNode')
            ->once()
            ->with($entry, 'About Us', null, null, false)
            ->andReturn($node);

        $result = app(CreateEntryTreeNode::class)->create($entry, 'About Us');

        $this->assertSame($node, $result);
    }

    public function test_create_tree_node_action_passes_parent_template_and_home_flag(): void
    {
        $entry = $this->makeTreeEntry();
        $parent = EntryTree::factory()->create(['entry_id' => $this->makeTreeEntry()->id]);
        $node = EntryTree::factory()->create(['entry_id' => $entry->id]);
        $service = $this->mock(EntryService::class);
        $service->shouldReceive('createTreeNode')
            ->once()
            ->with($entry, 'Child', $parent, 'entries.show', false)
            ->andReturn($node);

        $result = app(CreateEntryTreeNode::class)->create($entry, 'Child', $parent, 'entries.show', false);

        $this->assertSame($node, $result);
    }

    public function test_create_tree_node_action_returns_entry_tree_instance(): void
    {
        $entry = $this->makeTreeEntry();
        $node = EntryTree::factory()->create(['entry_id' => $entry->id]);
        $service = $this->mock(EntryService::class);
        $service->shouldReceive('createTreeNode')->once()->andReturn($node);

        $result = app(CreateEntryTreeNode::class)->create($entry, 'Page');

        $this->assertInstanceOf(EntryTree::class, $result);
    }

    // -------------------------------------------------------------------------
    // MoveEntryTreeNode action wrapper — delegation
    // -------------------------------------------------------------------------

    public function test_move_tree_node_action_delegates_to_service(): void
    {
        $node = EntryTree::factory()->create(['entry_id' => $this->makeTreeEntry()->id]);
        $parent = EntryTree::factory()->create(['entry_id' => $this->makeTreeEntry()->id]);
        $moved = EntryTree::factory()->create(['entry_id' => $this->makeTreeEntry()->id]);
        $service = $this->mock(EntryService::class);
        $service->shouldReceive('moveTreeNode')
            ->once()
            ->with($node, $parent, 2)
            ->andReturn($moved);

        $result = app(MoveEntryTreeNode::class)->handle($node, $parent, 2);

        $this->assertSame($moved, $result);
    }

    public function test_move_tree_node_action_returns_entry_tree_instance(): void
    {
        $node = EntryTree::factory()->create(['entry_id' => $this->makeTreeEntry()->id]);
        $moved = EntryTree::factory()->create(['entry_id' => $this->makeTreeEntry()->id]);
        $service = $this->mock(EntryService::class);
        $service->shouldReceive('moveTreeNode')->once()->andReturn($moved);

        $result = app(MoveEntryTreeNode::class)->handle($node, null, 0);

        $this->assertInstanceOf(EntryTree::class, $result);
    }

    // -------------------------------------------------------------------------
    // RebuildEntryTreeUri action wrapper — delegation
    // -------------------------------------------------------------------------

    public function test_rebuild_tree_uri_action_delegates_to_service(): void
    {
        $node = EntryTree::factory()->create(['entry_id' => $this->makeTreeEntry()->id]);
        $service = $this->mock(EntryService::class);
        $service->shouldReceive('rebuildTreeUri')
            ->once()
            ->with($node);

        app(RebuildEntryTreeUri::class)->handle($node);
    }

    public function test_rebuild_tree_uri_action_returns_void(): void
    {
        $node = EntryTree::factory()->create(['entry_id' => $this->makeTreeEntry()->id]);
        $service = $this->mock(EntryService::class);
        $service->shouldReceive('rebuildTreeUri')->once();

        $result = app(RebuildEntryTreeUri::class)->handle($node);

        $this->assertNull($result);
    }
}
