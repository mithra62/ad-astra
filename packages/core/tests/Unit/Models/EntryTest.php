<?php

namespace Tests\Unit\Models;

use AdAstra\Models\Entry;
use AdAstra\Models\EntryAuthor;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryType;
use AdAstra\Models\FieldLayout;
use AdAstra\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Fillable & Casts
    // -------------------------------------------------------------------------

    public function test_has_correct_fillable_attributes(): void
    {
        $entry = new Entry();

        $this->assertEquals(
            ['entry_group_id', 'entry_type_id', 'status_id', 'status_handle', 'status_is_public', 'title', 'handle', 'published_at'],
            $entry->getFillable()
        );
    }

    public function test_casts_published_at_to_carbon_datetime(): void
    {
        $entry = Entry::factory()->create(['published_at' => '2026-01-15 10:00:00']);

        $this->assertInstanceOf(Carbon::class, $entry->published_at);
        $this->assertEquals('2026-01-15', $entry->published_at->toDateString());
    }

    public function test_published_at_is_null_when_not_set(): void
    {
        $entry = Entry::factory()->create(['published_at' => null]);

        $this->assertNull($entry->published_at);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function test_entry_group_relationship_is_belongs_to(): void
    {
        $group = EntryGroup::factory()->create();
        $entry = Entry::factory()->for($group)->create();

        $this->assertInstanceOf(BelongsTo::class, $entry->entryGroup());
        $this->assertEquals($group->id, $entry->entryGroup->id);
    }

    public function test_entry_type_relationship_is_belongs_to(): void
    {
        $type = EntryType::factory()->create();
        $entry = Entry::factory()->for($type)->create();

        $this->assertInstanceOf(BelongsTo::class, $entry->entryType());
        $this->assertEquals($type->id, $entry->entryType->id);
    }

    public function test_creator_belongs_to_user_via_created_by_user_id_foreign_key(): void
    {
        $user = User::factory()->create();
        $entry = Entry::factory()->for($user, 'creator')->create();

        $this->assertInstanceOf(BelongsTo::class, $entry->creator());
        $this->assertEquals($user->id, $entry->creator->id);
        $this->assertEquals($user->id, $entry->created_by_user_id);
    }

    public function test_authors_relationship_is_belongs_to_many(): void
    {
        $entry = Entry::factory()->create();
        $ea1 = EntryAuthor::factory()->create();
        $ea2 = EntryAuthor::factory()->create();

        $entry->authors()->attach($ea1->id, ['sort_order' => 1]);
        $entry->authors()->attach($ea2->id, ['sort_order' => 2]);

        $this->assertInstanceOf(BelongsToMany::class, $entry->authors());
        $this->assertCount(2, $entry->authors()->get());
    }

    public function test_authors_pivot_exposes_sort_order(): void
    {
        $entry = Entry::factory()->create();
        $ea = EntryAuthor::factory()->create();

        $entry->authors()->attach($ea->id, ['sort_order' => 5]);

        $author = $entry->authors()->first();

        $this->assertEquals(5, $author->pivot->sort_order);
    }

    public function test_authors_are_ordered_by_sort_order_pivot(): void
    {
        $entry = Entry::factory()->create();
        $ea1 = EntryAuthor::factory()->create();
        $ea2 = EntryAuthor::factory()->create();

        $entry->authors()->attach($ea1->id, ['sort_order' => 2]);
        $entry->authors()->attach($ea2->id, ['sort_order' => 1]);

        $authors = $entry->authors()->get();

        // ea2 has sort_order=1 so comes first; ea1 has sort_order=2 so comes last
        $this->assertEquals($ea2->id, $authors->first()->id);
        $this->assertEquals($ea1->id, $authors->last()->id);
    }

    public function test_entry_relationships_is_has_many(): void
    {
        $entry = Entry::factory()->create();

        $this->assertInstanceOf(HasMany::class, $entry->entryRelationships());
    }

    // -------------------------------------------------------------------------
    // Traits
    // -------------------------------------------------------------------------

    public function test_has_field_values_morph_many_relationship(): void
    {
        $entry = Entry::factory()->create();

        $this->assertInstanceOf(MorphMany::class, $entry->fieldValues());
    }

    public function test_has_categories_morph_to_many_relationship(): void
    {
        $entry = Entry::factory()->create();

        $this->assertInstanceOf(MorphToMany::class, $entry->categories());
    }

    // -------------------------------------------------------------------------
    // field() method
    // -------------------------------------------------------------------------

    public function test_field_returns_scalar_value_by_handle(): void
    {
        $field = new class () {
            public string $handle = 'body';
        };

        $fieldValue = new class ($field) {
            public function __construct(public readonly object $field)
            {
            }

            public function resolvedValue(): mixed
            {
                return 'Hello World';
            }
        };

        $entry = new Entry();
        $entry->setRelation('fieldValues', collect([$fieldValue]));
        $entry->setRelation('entryRelationships', collect([]));

        $this->assertEquals('Hello World', $entry->field('body'));
    }

    public function test_field_returns_null_for_unknown_handle(): void
    {
        $entry = new Entry();
        $entry->setRelation('fieldValues', collect([]));
        $entry->setRelation('entryRelationships', collect([]));

        $this->assertNull($entry->field('nonexistent'));
    }

    public function test_field_does_not_return_scalar_value_for_wrong_handle(): void
    {
        $field = new class () {
            public string $handle = 'title';
        };

        $fieldValue = new class ($field) {
            public function __construct(public readonly object $field)
            {
            }

            public function resolvedValue(): mixed
            {
                return 'Some Title';
            }
        };

        $entry = new Entry();
        $entry->setRelation('fieldValues', collect([$fieldValue]));
        $entry->setRelation('entryRelationships', collect([]));

        $this->assertNull($entry->field('body'));
    }

    public function test_field_returns_collection_of_related_entries_for_relational_field(): void
    {
        $relatedEntry = new Entry(['title' => 'Related Entry']);

        $field = new class () {
            public string $handle = 'related_posts';
        };

        $relationship = new class ($field, $relatedEntry, 1) {
            public function __construct(
                public readonly object $field,
                public readonly Entry  $relatedEntry,
                public readonly int    $sort_order,
            ) {
            }
        };

        $entry = new Entry();
        $entry->setRelation('fieldValues', collect([]));
        $entry->setRelation('entryRelationships', collect([$relationship]));

        $result = $entry->field('related_posts');

        $this->assertCount(1, $result);
        $this->assertSame($relatedEntry, $result->first());
    }

    public function test_field_returns_null_when_relational_field_has_no_entries(): void
    {
        $field = new class () {
            public string $handle = 'related_posts';
        };

        /** Simulates a broken FK where relatedEntry is null. */
        $relationship = new class ($field, null, 1) {
            public function __construct(
                public readonly object $field,
                public readonly ?Entry $relatedEntry,
                public readonly int    $sort_order,
            ) {
            }
        };

        $entry = new Entry();
        $entry->setRelation('fieldValues', collect([]));
        $entry->setRelation('entryRelationships', collect([$relationship]));

        $this->assertNull($entry->field('related_posts'));
    }

    public function test_field_returns_related_entries_ordered_by_sort_order(): void
    {
        $first = new Entry(['title' => 'First']);
        $second = new Entry(['title' => 'Second']);

        $field = new class () {
            public string $handle = 'related_posts';
        };

        $makeRel = fn (Entry $e, int $order) => new class ($field, $e, $order) {
            public function __construct(
                public readonly object $field,
                public readonly Entry  $relatedEntry,
                public readonly int    $sort_order,
            ) {
            }
        };

        $entry = new Entry();
        $entry->setRelation('fieldValues', collect([]));
        // Intentionally insert in reverse sort order.
        $entry->setRelation('entryRelationships', collect([$makeRel($second, 2), $makeRel($first, 1)]));

        $result = $entry->field('related_posts');

        $this->assertCount(2, $result);
        $this->assertSame($first, $result->first());
        $this->assertSame($second, $result->last());
    }

    // -------------------------------------------------------------------------
    // getFieldLayout()
    // -------------------------------------------------------------------------

    public function test_get_field_layout_returns_entry_type_layout_when_available(): void
    {
        $typeLayout = new FieldLayout();

        $entryType = new EntryType();
        $entryType->setRelation('fieldLayout', $typeLayout);

        $entryGroup = new EntryGroup();
        $entryGroup->setRelation('fieldLayout', new FieldLayout());

        $entry = new Entry();
        $entry->setRelation('entryType', $entryType);
        $entry->setRelation('entryGroup', $entryGroup);

        $this->assertSame($typeLayout, $entry->getFieldLayout());
    }

    public function test_get_field_layout_falls_back_to_entry_group_layout(): void
    {
        $entryType = new EntryType();
        $entryType->setRelation('fieldLayout', null);

        $groupLayout = new FieldLayout();
        $entryGroup = new EntryGroup();
        $entryGroup->setRelation('fieldLayout', $groupLayout);

        $entry = new Entry();
        $entry->setRelation('entryType', $entryType);
        $entry->setRelation('entryGroup', $entryGroup);

        $this->assertSame($groupLayout, $entry->getFieldLayout());
    }

    // -------------------------------------------------------------------------
    // scopePublished
    // -------------------------------------------------------------------------

    public function test_scope_published_returns_entries_with_past_published_at_and_public_status(): void
    {
        $publishedEntry = Entry::factory()->create([
            'published_at' => now()->subHour(),
            'status_is_public' => true,
        ]);

        $results = Entry::query()->published()->get();

        $this->assertTrue($results->contains($publishedEntry));
    }

    public function test_scope_published_excludes_entries_with_future_published_at(): void
    {
        $futureEntry = Entry::factory()->create([
            'published_at' => now()->addDay(),
            'status_is_public' => true,
        ]);

        $results = Entry::query()->published()->get();

        $this->assertFalse($results->contains($futureEntry));
    }

    public function test_scope_published_excludes_entries_with_null_published_at(): void
    {
        $draftEntry = Entry::factory()->create([
            'published_at' => null,
            'status_is_public' => true,
        ]);

        $results = Entry::query()->published()->get();

        $this->assertFalse($results->contains($draftEntry));
    }

    public function test_scope_published_excludes_non_public_status_with_past_published_at(): void
    {
        $draftEntry = Entry::factory()->create([
            'published_at' => now()->subHour(),
            'status_is_public' => false,
        ]);

        $results = Entry::query()->published()->get();

        $this->assertFalse($results->contains($draftEntry));
    }

    // -------------------------------------------------------------------------
    // scopeWithStatus
    // -------------------------------------------------------------------------

    public function test_scope_with_status_returns_matching_entries(): void
    {
        $activeEntry = Entry::factory()->create(['status_handle' => 'active']);
        Entry::factory()->create(['status_handle' => 'draft']);

        $results = Entry::query()->withStatus('active')->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->contains($activeEntry));
    }

    public function test_scope_with_status_excludes_non_matching_entries(): void
    {
        $draftEntry = Entry::factory()->create(['status_handle' => 'draft']);

        $results = Entry::query()->withStatus('active')->get();

        $this->assertFalse($results->contains($draftEntry));
    }

    // -------------------------------------------------------------------------
    // scopeInGroup
    // -------------------------------------------------------------------------

    public function test_scope_in_group_filters_by_entry_group_model(): void
    {
        $group1 = EntryGroup::factory()->create();
        $group2 = EntryGroup::factory()->create();
        $entry1 = Entry::factory()->for($group1)->create();
        $entry2 = Entry::factory()->for($group2)->create();

        $results = Entry::query()->inGroup($group1)->get();

        $this->assertTrue($results->contains($entry1));
        $this->assertFalse($results->contains($entry2));
    }

    public function test_scope_in_group_filters_by_group_handle_string(): void
    {
        $group1 = EntryGroup::factory()->create(['handle' => 'blog-posts']);
        $group2 = EntryGroup::factory()->create(['handle' => 'news-items']);
        $entry1 = Entry::factory()->for($group1)->create();
        $entry2 = Entry::factory()->for($group2)->create();

        $results = Entry::query()->inGroup('blog-posts')->get();

        $this->assertTrue($results->contains($entry1));
        $this->assertFalse($results->contains($entry2));
    }

    public function test_scope_in_group_filters_by_group_id_integer(): void
    {
        $group1 = EntryGroup::factory()->create();
        $group2 = EntryGroup::factory()->create();
        $entry1 = Entry::factory()->for($group1)->create();
        $entry2 = Entry::factory()->for($group2)->create();

        $results = Entry::query()->inGroup($group1->id)->get();

        $this->assertTrue($results->contains($entry1));
        $this->assertFalse($results->contains($entry2));
    }

    // -------------------------------------------------------------------------
    // scopeOfType
    // -------------------------------------------------------------------------

    public function test_scope_of_type_filters_by_entry_type_model(): void
    {
        $group = EntryGroup::factory()->create();
        $type1 = EntryType::factory()->for($group)->create();
        $type2 = EntryType::factory()->for($group)->create();
        $entry1 = Entry::factory()->for($group)->for($type1)->create();
        $entry2 = Entry::factory()->for($group)->for($type2)->create();

        $results = Entry::query()->ofType($type1)->get();

        $this->assertTrue($results->contains($entry1));
        $this->assertFalse($results->contains($entry2));
    }

    public function test_scope_of_type_filters_by_type_handle_string(): void
    {
        $group = EntryGroup::factory()->create();
        $type1 = EntryType::factory()->for($group)->create(['handle' => 'article']);
        $type2 = EntryType::factory()->for($group)->create(['handle' => 'product']);
        $entry1 = Entry::factory()->for($group)->for($type1)->create();
        $entry2 = Entry::factory()->for($group)->for($type2)->create();

        $results = Entry::query()->ofType('article')->get();

        $this->assertTrue($results->contains($entry1));
        $this->assertFalse($results->contains($entry2));
    }

    public function test_scope_of_type_filters_by_type_id_integer(): void
    {
        $group = EntryGroup::factory()->create();
        $type1 = EntryType::factory()->for($group)->create();
        $type2 = EntryType::factory()->for($group)->create();
        $entry1 = Entry::factory()->for($group)->for($type1)->create();
        $entry2 = Entry::factory()->for($group)->for($type2)->create();

        $results = Entry::query()->ofType($type1->id)->get();

        $this->assertTrue($results->contains($entry1));
        $this->assertFalse($results->contains($entry2));
    }
}
