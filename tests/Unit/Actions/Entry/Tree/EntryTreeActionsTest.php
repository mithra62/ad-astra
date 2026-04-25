<?php

namespace Tests\Unit\Actions\Entry\Tree;

use App\Actions\Entry\Tree\CreateEntryTreeNode;
use App\Actions\Entry\Tree\MoveEntryTreeNode;
use App\Actions\Entry\Tree\RebuildEntryTreeUri;
use App\Models\Entry;
use App\Models\EntryGroup;
use App\Models\EntryTree;
use App\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class EntryTreeActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_entry_tree_node_builds_handle_uri_and_depth(): void
    {
        $action = app(CreateEntryTreeNode::class);
        $parentEntry = $this->makeTreeEntry();
        $childEntry = $this->makeTreeEntry();

        $parent = $action->create($parentEntry, 'About Us');
        $child = $action->create($childEntry, 'Leadership Team', $parent);

        $this->assertSame('about-us', $parent->handle);
        $this->assertSame('about-us', $parent->uri);
        $this->assertSame(0, $parent->depth);
        $this->assertSame('leadership-team', $child->handle);
        $this->assertSame('about-us/leadership-team', $child->uri);
        $this->assertSame(1, $child->depth);
        $this->assertSame($childEntry->entryType->id, $child->entry->entryType->id);
    }

    public function test_create_entry_tree_node_rejects_invalid_handles(): void
    {
        $action = app(CreateEntryTreeNode::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Entry Tree handles must contain at least one URL-safe character.');

        $action->create($this->makeTreeEntry(), '!!!');
    }

    public function test_create_entry_tree_node_enforces_home_node_invariants(): void
    {
        $action = app(CreateEntryTreeNode::class);
        $parent = $action->create($this->makeTreeEntry(), 'Root');

        $home = $action->create($this->makeTreeEntry(), 'Home', null, null, true);

        $this->assertSame('/', $home->uri);

        try {
            $action->create($this->makeTreeEntry(), 'Nested Home', $parent, null, true);
            $this->fail('Expected nested home creation to fail.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('The Entry Tree home node must be a root node.', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only one Entry Tree home node may exist.');

        $action->create($this->makeTreeEntry(), 'Second Home', null, null, true);
    }

    public function test_create_entry_tree_node_prevents_duplicate_root_handles(): void
    {
        $action = app(CreateEntryTreeNode::class);

        $action->create($this->makeTreeEntry(), 'About');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An Entry Tree node with handle [about] already exists at this level.');

        $action->create($this->makeTreeEntry(), 'About');
    }

    public function test_move_entry_tree_node_rebuilds_subtree_and_rebalances_sort_order(): void
    {
        $create = app(CreateEntryTreeNode::class);
        $move = app(MoveEntryTreeNode::class);

        $rootA = $create->create($this->makeTreeEntry(), 'Section A');
        $rootB = $create->create($this->makeTreeEntry(), 'Section B');
        $firstChild = $create->create($this->makeTreeEntry(), 'First Child', $rootA);
        $movedChild = $create->create($this->makeTreeEntry(), 'Moved Child', $rootA);
        $grandchild = $create->create($this->makeTreeEntry(), 'Grand Child', $movedChild);

        $moved = $move->handle($movedChild, $rootB, 1);

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
        $create = app(CreateEntryTreeNode::class);
        $move = app(MoveEntryTreeNode::class);

        $rootA = $create->create($this->makeTreeEntry(), 'Section A');
        $rootB = $create->create($this->makeTreeEntry(), 'Section B');
        $candidate = $create->create($this->makeTreeEntry(), 'Child', $rootA);
        $create->create($this->makeTreeEntry(), 'Child', $rootB);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An Entry Tree node with handle [child] already exists at this level.');

        $move->handle($candidate, $rootB, 1);
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

        app(RebuildEntryTreeUri::class)->handle($invalidHome);
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
}
