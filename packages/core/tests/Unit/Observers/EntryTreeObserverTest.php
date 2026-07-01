<?php

namespace Tests\Unit\Observers;

use AdAstra\Models\Entry;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryTree;
use AdAstra\Models\EntryType;
use AdAstra\Services\EntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for EntryTreeObserver.
 *
 * The core invariant: when a parent EntryTree node is deleted, the DB-level
 * nullOnDelete constraint promotes every direct child to a root node.  The
 * observer must then rebuild each promoted child's `depth` and `uri` columns
 * so they reflect their new position in the tree.
 */
class EntryTreeObserverTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create an Entry that belongs to an EntryType with has_entry_tree = true.
     * Mirrors the helper pattern used across other tree-related tests.
     */
    private function makeTreeEntry(): Entry
    {
        $group = EntryGroup::factory()->create();
        $type  = EntryType::factory()->for($group)->create([
            'has_entry_tree'   => true,
            'default_template' => 'entries.page',
        ]);

        return Entry::factory()->for($group)->for($type)->create();
    }

    /** Shorthand: create a root tree node via the service. */
    private function makeRoot(string $handle = 'root'): EntryTree
    {
        return app(EntryService::class)->createTreeNode($this->makeTreeEntry(), $handle);
    }

    /** Shorthand: create a child node under an existing parent. */
    private function makeChild(EntryTree $parent, string $handle): EntryTree
    {
        return app(EntryService::class)->createTreeNode($this->makeTreeEntry(), $handle, $parent);
    }

    // -------------------------------------------------------------------------
    // Depth rebuild on parent deletion
    // -------------------------------------------------------------------------

    public function test_direct_child_depth_is_reset_to_zero_after_parent_deleted(): void
    {
        $parent = $this->makeRoot('blog');
        $child  = $this->makeChild($parent, 'posts');

        $this->assertEquals(1, $child->depth);

        $parent->delete();

        $this->assertEquals(0, $child->fresh()->depth);
    }

    public function test_grandchild_depth_is_decremented_after_grandparent_deleted(): void
    {
        $grandparent = $this->makeRoot('section');
        $parent      = $this->makeChild($grandparent, 'category');
        $grandchild  = $this->makeChild($parent, 'post');

        $this->assertEquals(2, $grandchild->depth);

        // Deleting grandparent promotes parent to root (depth 0) and
        // grandchild should cascade to depth 1.
        $grandparent->delete();

        $this->assertEquals(0, $parent->fresh()->depth);
        $this->assertEquals(1, $grandchild->fresh()->depth);
    }

    // -------------------------------------------------------------------------
    // URI rebuild on parent deletion
    // -------------------------------------------------------------------------

    public function test_direct_child_uri_is_rebuilt_to_its_own_handle_after_parent_deleted(): void
    {
        $parent = $this->makeRoot('blog');
        $child  = $this->makeChild($parent, 'posts');

        $this->assertEquals('blog/posts', $child->uri);

        $parent->delete();

        $this->assertEquals('posts', $child->fresh()->uri);
    }

    public function test_grandchild_uri_is_rebuilt_after_grandparent_deleted(): void
    {
        $grandparent = $this->makeRoot('news');
        $parent      = $this->makeChild($grandparent, '2025');
        $grandchild  = $this->makeChild($parent, 'my-article');

        $this->assertEquals('news/2025/my-article', $grandchild->uri);

        $grandparent->delete();

        $this->assertEquals('2025', $parent->fresh()->uri);
        $this->assertEquals('2025/my-article', $grandchild->fresh()->uri);
    }

    // -------------------------------------------------------------------------
    // Multiple children
    // -------------------------------------------------------------------------

    public function test_all_direct_children_are_rebuilt_after_parent_deleted(): void
    {
        $parent = $this->makeRoot('docs');
        $childA = $this->makeChild($parent, 'intro');
        $childB = $this->makeChild($parent, 'guide');
        $childC = $this->makeChild($parent, 'api');

        $parent->delete();

        $this->assertEquals(0, $childA->fresh()->depth);
        $this->assertEquals(0, $childB->fresh()->depth);
        $this->assertEquals(0, $childC->fresh()->depth);

        $this->assertEquals('intro', $childA->fresh()->uri);
        $this->assertEquals('guide', $childB->fresh()->uri);
        $this->assertEquals('api',   $childC->fresh()->uri);
    }

    // -------------------------------------------------------------------------
    // deleteTreeNode() service method
    // -------------------------------------------------------------------------

    public function test_delete_tree_node_removes_node_from_database(): void
    {
        $node = $this->makeRoot('about');

        app(EntryService::class)->deleteTreeNode($node);

        $this->assertDatabaseMissing('entry_trees', ['id' => $node->id]);
    }

    public function test_delete_tree_node_triggers_observer_and_rebuilds_child(): void
    {
        $parent = $this->makeRoot('help');
        $child  = $this->makeChild($parent, 'faq');

        $this->assertEquals(1, $child->depth);
        $this->assertEquals('help/faq', $child->uri);

        app(EntryService::class)->deleteTreeNode($parent);

        $this->assertEquals(0,     $child->fresh()->depth);
        $this->assertEquals('faq', $child->fresh()->uri);
    }

    public function test_delete_tree_node_returns_true_on_success(): void
    {
        $node   = $this->makeRoot('terms');
        $result = app(EntryService::class)->deleteTreeNode($node);

        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // Leaf node deletion (no children — nothing to rebuild)
    // -------------------------------------------------------------------------

    public function test_deleting_a_leaf_node_does_not_affect_its_siblings(): void
    {
        $parent  = $this->makeRoot('portfolio');
        $sibling = $this->makeChild($parent, 'project-a');
        $leaf    = $this->makeChild($parent, 'project-b');

        $leaf->delete();

        $this->assertDatabaseMissing('entry_trees', ['id' => $leaf->id]);

        // Sibling's depth and URI are untouched.
        $this->assertEquals(1,                    $sibling->fresh()->depth);
        $this->assertEquals('portfolio/project-a', $sibling->fresh()->uri);
    }

    public function test_deleting_a_root_leaf_leaves_db_clean(): void
    {
        $root = $this->makeRoot('contact');

        $root->delete();

        $this->assertDatabaseMissing('entry_trees', ['id' => $root->id]);
    }

    // -------------------------------------------------------------------------
    // Home node edge case
    // -------------------------------------------------------------------------

    public function test_deleting_parent_of_non_home_root_preserves_home_node(): void
    {
        $home   = app(EntryService::class)->createTreeNode($this->makeTreeEntry(), 'home', null, null, true);
        $parent = $this->makeRoot('blog');
        $child  = $this->makeChild($parent, 'post');

        $parent->delete();

        // Home node is unaffected.
        $this->assertDatabaseHas('entry_trees', ['id' => $home->id, 'uri' => '/']);

        // Child was rebuilt correctly.
        $this->assertEquals(0,      $child->fresh()->depth);
        $this->assertEquals('post', $child->fresh()->uri);
    }
}
