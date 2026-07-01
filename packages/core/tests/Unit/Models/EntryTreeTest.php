<?php

namespace Tests\Unit\Models;

use AdAstra\Models\Entry;
use AdAstra\Models\EntryTree;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class EntryTreeTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Fillable & Casts
    // -------------------------------------------------------------------------

    public function test_has_correct_fillable_attributes(): void
    {
        $model = new EntryTree;

        $this->assertEquals(
            ['entry_id', 'parent_id', 'handle', 'uri', 'depth', 'sort_order', 'redirect_url', 'redirect_status', 'template', 'is_home'],
            $model->getFillable()
        );
    }

    public function test_casts_is_home_to_boolean(): void
    {
        $entry = Entry::factory()->create();
        $node = EntryTree::create([
            'entry_id' => $entry->id,
            'handle' => 'home',
            'uri' => '/',
            'depth' => 0,
            'sort_order' => 0,
            'is_home' => 1,
        ]);

        $this->assertIsBool($node->is_home);
        $this->assertTrue($node->is_home);
    }

    public function test_is_home_defaults_to_false(): void
    {
        $entry = Entry::factory()->create();
        $node = EntryTree::create([
            'entry_id' => $entry->id,
            'handle' => 'about',
            'uri' => 'about',
            'depth' => 0,
            'sort_order' => 0,
        ]);

        $this->assertFalse($node->fresh()->is_home);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function test_entry_relationship_is_belongs_to(): void
    {
        $node = new EntryTree;

        $this->assertInstanceOf(BelongsTo::class, $node->entry());
    }

    public function test_entry_relationship_returns_associated_entry(): void
    {
        $entry = Entry::factory()->create();
        $node = EntryTree::create([
            'entry_id' => $entry->id,
            'handle' => 'about',
            'uri' => 'about',
            'depth' => 0,
            'sort_order' => 0,
        ]);

        $this->assertEquals($entry->id, $node->entry->id);
    }

    public function test_parent_relationship_is_belongs_to_self(): void
    {
        $node = new EntryTree;

        $this->assertInstanceOf(BelongsTo::class, $node->parent());
    }

    public function test_parent_relationship_returns_parent_node(): void
    {
        $parentEntry = Entry::factory()->create();
        $childEntry = Entry::factory()->create();

        $parent = EntryTree::create([
            'entry_id' => $parentEntry->id,
            'handle' => 'blog',
            'uri' => 'blog',
            'depth' => 0,
            'sort_order' => 0,
        ]);

        $child = EntryTree::create([
            'entry_id' => $childEntry->id,
            'parent_id' => $parent->id,
            'handle' => 'post',
            'uri' => 'blog/post',
            'depth' => 1,
            'sort_order' => 0,
        ]);

        $this->assertEquals($parent->id, $child->parent->id);
    }

    public function test_parent_is_null_for_root_node(): void
    {
        $entry = Entry::factory()->create();
        $root = EntryTree::create([
            'entry_id' => $entry->id,
            'handle' => 'home',
            'uri' => '/',
            'depth' => 0,
            'sort_order' => 0,
        ]);

        $this->assertNull($root->parent);
    }

    public function test_children_relationship_is_has_many(): void
    {
        $node = new EntryTree;

        $this->assertInstanceOf(HasMany::class, $node->children());
    }

    public function test_children_relationship_returns_child_nodes(): void
    {
        $parentEntry = Entry::factory()->create();
        $child1Entry = Entry::factory()->create();
        $child2Entry = Entry::factory()->create();

        $parent = EntryTree::create([
            'entry_id' => $parentEntry->id,
            'handle' => 'blog',
            'uri' => 'blog',
            'depth' => 0,
            'sort_order' => 0,
        ]);

        EntryTree::create([
            'entry_id' => $child1Entry->id,
            'parent_id' => $parent->id,
            'handle' => 'post-one',
            'uri' => 'blog/post-one',
            'depth' => 1,
            'sort_order' => 1,
        ]);

        EntryTree::create([
            'entry_id' => $child2Entry->id,
            'parent_id' => $parent->id,
            'handle' => 'post-two',
            'uri' => 'blog/post-two',
            'depth' => 1,
            'sort_order' => 2,
        ]);

        $this->assertCount(2, $parent->children);
    }

    public function test_children_are_ordered_by_sort_order(): void
    {
        $parentEntry = Entry::factory()->create();
        $firstEntry = Entry::factory()->create();
        $secondEntry = Entry::factory()->create();

        $parent = EntryTree::create([
            'entry_id' => $parentEntry->id,
            'handle' => 'section',
            'uri' => 'section',
            'depth' => 0,
            'sort_order' => 0,
        ]);

        // Insert second child first to ensure ordering is by sort_order, not insertion order.
        EntryTree::create([
            'entry_id' => $secondEntry->id,
            'parent_id' => $parent->id,
            'handle' => 'second',
            'uri' => 'section/second',
            'depth' => 1,
            'sort_order' => 2,
        ]);

        EntryTree::create([
            'entry_id' => $firstEntry->id,
            'parent_id' => $parent->id,
            'handle' => 'first',
            'uri' => 'section/first',
            'depth' => 1,
            'sort_order' => 1,
        ]);

        $children = $parent->children;

        $this->assertEquals('first', $children->first()->handle);
        $this->assertEquals('second', $children->last()->handle);
    }

    public function test_leaf_node_has_no_children(): void
    {
        $entry = Entry::factory()->create();
        $node = EntryTree::create([
            'entry_id' => $entry->id,
            'handle' => 'leaf',
            'uri' => 'leaf',
            'depth' => 0,
            'sort_order' => 0,
        ]);

        $this->assertCount(0, $node->children);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function test_scope_root_returns_nodes_with_null_parent_id(): void
    {
        $rootEntry = Entry::factory()->create();
        $childEntry = Entry::factory()->create();

        $root = EntryTree::create([
            'entry_id' => $rootEntry->id,
            'handle' => 'home',
            'uri' => '/',
            'depth' => 0,
            'sort_order' => 0,
        ]);

        $child = EntryTree::create([
            'entry_id' => $childEntry->id,
            'parent_id' => $root->id,
            'handle' => 'about',
            'uri' => 'about',
            'depth' => 1,
            'sort_order' => 1,
        ]);

        $roots = EntryTree::query()->root()->get();

        $this->assertTrue($roots->contains($root));
        $this->assertFalse($roots->contains($child));
    }

    public function test_scope_by_uri_finds_node_by_exact_uri(): void
    {
        $entry = Entry::factory()->create();
        $node = EntryTree::create([
            'entry_id' => $entry->id,
            'handle' => 'about',
            'uri' => 'about',
            'depth' => 0,
            'sort_order' => 0,
        ]);

        $result = EntryTree::query()->byUri('about')->first();

        $this->assertNotNull($result);
        $this->assertEquals($node->id, $result->id);
    }

    public function test_scope_by_uri_normalizes_leading_and_trailing_slashes(): void
    {
        $entry = Entry::factory()->create();
        $node = EntryTree::create([
            'entry_id' => $entry->id,
            'handle' => 'about',
            'uri' => 'about',
            'depth' => 0,
            'sort_order' => 0,
        ]);

        $result = EntryTree::query()->byUri('/about/')->first();

        $this->assertNotNull($result);
        $this->assertEquals($node->id, $result->id);
    }

    public function test_scope_by_uri_finds_root_node_with_slash(): void
    {
        $entry = Entry::factory()->create();
        $node = EntryTree::create([
            'entry_id' => $entry->id,
            'handle' => 'home',
            'uri' => '/',
            'depth' => 0,
            'sort_order' => 0,
        ]);

        $result = EntryTree::query()->byUri('/')->first();

        $this->assertNotNull($result);
        $this->assertEquals($node->id, $result->id);
    }

    // -------------------------------------------------------------------------
    // getUrlAttribute
    // -------------------------------------------------------------------------

    public function test_get_url_attribute_returns_slash_for_root_uri(): void
    {
        $node = new EntryTree;
        $node->uri = '/';

        $this->assertEquals('/', $node->url);
    }

    public function test_get_url_attribute_prepends_slash_for_non_root_uri(): void
    {
        $node = new EntryTree;
        $node->uri = 'blog/post';

        $this->assertEquals('/blog/post', $node->url);
    }

    public function test_get_url_attribute_prepends_slash_for_single_segment_uri(): void
    {
        $node = new EntryTree;
        $node->uri = 'about';

        $this->assertEquals('/about', $node->url);
    }

    // -------------------------------------------------------------------------
    // normalizeHandle
    // -------------------------------------------------------------------------

    public function test_normalize_handle_slugifies_the_handle(): void
    {
        $this->assertEquals('my-handle', EntryTree::normalizeHandle('My Handle'));
    }

    public function test_normalize_handle_lowercases_and_strips_special_chars(): void
    {
        $this->assertEquals('hello-world', EntryTree::normalizeHandle('Hello World!'));
    }

    public function test_normalize_handle_returns_empty_string_for_non_url_safe_input(): void
    {
        $this->assertEquals('', EntryTree::normalizeHandle('!!!'));
    }

    public function test_normalize_handle_leaves_already_normalized_handle_unchanged(): void
    {
        $this->assertEquals('my-page', EntryTree::normalizeHandle('my-page'));
    }

    // -------------------------------------------------------------------------
    // validatedHandle
    // -------------------------------------------------------------------------

    public function test_validated_handle_returns_normalized_handle_for_valid_input(): void
    {
        $this->assertEquals('my-page', EntryTree::validatedHandle('My Page'));
    }

    public function test_validated_handle_throws_for_empty_normalized_result(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Entry Tree handles must contain at least one URL-safe character.');

        EntryTree::validatedHandle('!!!');
    }

    public function test_validated_handle_throws_for_blank_string(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EntryTree::validatedHandle('   ');
    }

    public function test_validated_handle_accepts_alphanumeric_handle(): void
    {
        $this->assertEquals('about-us', EntryTree::validatedHandle('about-us'));
    }

    // -------------------------------------------------------------------------
    // normalizeUri
    // -------------------------------------------------------------------------

    public function test_normalize_uri_returns_slash_for_null(): void
    {
        $this->assertEquals('/', EntryTree::normalizeUri(null));
    }

    public function test_normalize_uri_returns_slash_for_empty_string(): void
    {
        $this->assertEquals('/', EntryTree::normalizeUri(''));
    }

    public function test_normalize_uri_returns_slash_for_lone_slash(): void
    {
        $this->assertEquals('/', EntryTree::normalizeUri('/'));
    }

    public function test_normalize_uri_strips_leading_and_trailing_slashes(): void
    {
        $this->assertEquals('blog/post', EntryTree::normalizeUri('/blog/post/'));
    }

    public function test_normalize_uri_strips_only_trailing_slash(): void
    {
        $this->assertEquals('about', EntryTree::normalizeUri('about/'));
    }

    public function test_normalize_uri_leaves_already_normalized_uri_unchanged(): void
    {
        $this->assertEquals('about/team', EntryTree::normalizeUri('about/team'));
    }

    public function test_normalize_uri_handles_deeply_nested_path(): void
    {
        $this->assertEquals('a/b/c/d', EntryTree::normalizeUri('/a/b/c/d/'));
    }
}
