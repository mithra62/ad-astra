<?php

namespace Tests\Unit\Builders;

use App\Builders\EntryQueryBuilder;
use App\Field\Types\Relationship;
use App\Field\Types\Text;
use App\Models\Category;
use App\Models\Entry;
use App\Models\EntryAuthor;
use App\Models\EntryGroup;
use App\Models\EntryType;
use App\Models\Field;
use App\Models\Field\Type;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;
use App\Models\FieldValue;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Tests\TestCase;

class EntryQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_group_returns_self(): void
    {
        $group = EntryGroup::factory()->create();
        $builder = $this->builder();

        $this->assertSame($builder, $builder->inGroup($group));
    }

    // -------------------------------------------------------------------------
    // Fluent interface – all chainable methods must return the same instance
    // -------------------------------------------------------------------------

    private function builder(): EntryQueryBuilder
    {
        return new EntryQueryBuilder();
    }

    /**
     * Create an Entry wired to a layout containing a single Text field with
     * the given handle. Returns [$entry, $field] for use in assertions.
     */
    private function makeEntryWithTextField(string $handle): array
    {
        $fieldType = Type::firstOrCreate(
            ['object' => Text::class],
            ['name' => 'Text', 'settings' => []]
        );
        $field     = Field::factory()->create(['field_type_id' => $fieldType->id, 'handle' => $handle]);

        $layout  = FieldLayout::factory()->create();
        $tab     = Tab::factory()->create(['field_layout_id' => $layout->id]);
        TabElement::factory()->create(['field_layout_tab_id' => $tab->id, 'field_id' => $field->id]);

        $group = EntryGroup::factory()->create(['field_layout_id' => $layout->id]);
        $type  = EntryType::factory()->create(['entry_group_id' => $group->id, 'field_layout_id' => null]);
        $entry = Entry::factory()->create(['entry_group_id' => $group->id, 'entry_type_id' => $type->id]);

        return [$entry, $field];
    }

    /**
     * Store a text value for the given field on the given entry.
     */
    private function storeTextValue(Entry $entry, Field $field, string $value): void
    {
        FieldValue::create([
            'field_id'       => $field->id,
            'fieldable_id'   => $entry->id,
            'fieldable_type' => $entry->getMorphClass(),
            'value_text'     => $value,
        ]);
    }

    public function test_of_type_returns_self(): void
    {
        $type = EntryType::factory()->create();
        $builder = $this->builder();

        $this->assertSame($builder, $builder->ofType($type));
    }

    public function test_published_returns_self(): void
    {
        $builder = $this->builder();

        $this->assertSame($builder, $builder->published());
    }

    public function test_with_status_returns_self(): void
    {
        $builder = $this->builder();

        $this->assertSame($builder, $builder->withStatus('draft'));
    }

    public function test_with_author_returns_self(): void
    {
        $builder = $this->builder();

        $this->assertSame($builder, $builder->withAuthor(1));
    }

    public function test_with_category_returns_self(): void
    {
        $builder = $this->builder();

        $this->assertSame($builder, $builder->withCategory(1));
    }

    public function test_where_returns_self(): void
    {
        $builder = $this->builder();

        $this->assertSame($builder, $builder->where('status_handle', 'draft'));
    }

    public function test_order_by_returns_self(): void
    {
        $builder = $this->builder();

        $this->assertSame($builder, $builder->orderBy('created_at'));
    }

    public function test_latest_returns_self(): void
    {
        $builder = $this->builder();

        $this->assertSame($builder, $builder->latest());
    }

    // -------------------------------------------------------------------------
    // inGroup()
    // -------------------------------------------------------------------------

    public function test_in_group_filters_by_entry_group_model(): void
    {
        $group1 = EntryGroup::factory()->create();
        $group2 = EntryGroup::factory()->create();
        $entry1 = Entry::factory()->for($group1)->create();
        $entry2 = Entry::factory()->for($group2)->create();

        $results = $this->builder()->inGroup($group1)->get();

        $this->assertTrue($results->contains('id', $entry1->id));
        $this->assertFalse($results->contains('id', $entry2->id));
    }

    public function test_in_group_filters_by_group_handle_string(): void
    {
        $group1 = EntryGroup::factory()->create(['handle' => 'blog-posts']);
        $group2 = EntryGroup::factory()->create(['handle' => 'news']);
        $entry1 = Entry::factory()->for($group1)->create();
        $entry2 = Entry::factory()->for($group2)->create();

        $results = $this->builder()->inGroup('blog-posts')->get();

        $this->assertTrue($results->contains('id', $entry1->id));
        $this->assertFalse($results->contains('id', $entry2->id));
    }

    public function test_in_group_filters_by_group_id_integer(): void
    {
        $group1 = EntryGroup::factory()->create();
        $group2 = EntryGroup::factory()->create();
        $entry1 = Entry::factory()->for($group1)->create();
        $entry2 = Entry::factory()->for($group2)->create();

        $results = $this->builder()->inGroup($group1->id)->get();

        $this->assertTrue($results->contains('id', $entry1->id));
        $this->assertFalse($results->contains('id', $entry2->id));
    }

    // -------------------------------------------------------------------------
    // ofType()
    // -------------------------------------------------------------------------

    public function test_of_type_filters_by_entry_type_model(): void
    {
        $group = EntryGroup::factory()->create();
        $type1 = EntryType::factory()->for($group)->create();
        $type2 = EntryType::factory()->for($group)->create();
        $entry1 = Entry::factory()->for($group)->for($type1)->create();
        $entry2 = Entry::factory()->for($group)->for($type2)->create();

        $results = $this->builder()->ofType($type1)->get();

        $this->assertTrue($results->contains('id', $entry1->id));
        $this->assertFalse($results->contains('id', $entry2->id));
    }

    public function test_of_type_filters_by_type_handle_string(): void
    {
        $group = EntryGroup::factory()->create();
        $type1 = EntryType::factory()->for($group)->create(['handle' => 'article']);
        $type2 = EntryType::factory()->for($group)->create(['handle' => 'podcast']);
        $entry1 = Entry::factory()->for($group)->for($type1)->create();
        $entry2 = Entry::factory()->for($group)->for($type2)->create();

        $results = $this->builder()->ofType('article')->get();

        $this->assertTrue($results->contains('id', $entry1->id));
        $this->assertFalse($results->contains('id', $entry2->id));
    }

    public function test_of_type_filters_by_type_id_integer(): void
    {
        $group = EntryGroup::factory()->create();
        $type1 = EntryType::factory()->for($group)->create();
        $type2 = EntryType::factory()->for($group)->create();
        $entry1 = Entry::factory()->for($group)->for($type1)->create();
        $entry2 = Entry::factory()->for($group)->for($type2)->create();

        $results = $this->builder()->ofType($type1->id)->get();

        $this->assertTrue($results->contains('id', $entry1->id));
        $this->assertFalse($results->contains('id', $entry2->id));
    }

    // -------------------------------------------------------------------------
    // published()
    // -------------------------------------------------------------------------

    public function test_published_returns_entries_with_past_published_at_and_public_status(): void
    {
        $published = Entry::factory()->create([
            'status_is_public' => true,
            'published_at' => now()->subHour(),
        ]);

        $results = $this->builder()->published()->get();

        $this->assertTrue($results->contains('id', $published->id));
    }

    public function test_published_excludes_entries_with_future_published_at(): void
    {
        $future = Entry::factory()->create([
            'status_is_public' => true,
            'published_at' => now()->addDay(),
        ]);

        $results = $this->builder()->published()->get();

        $this->assertFalse($results->contains('id', $future->id));
    }

    public function test_published_excludes_entries_with_null_published_at(): void
    {
        $draft = Entry::factory()->create([
            'status_is_public' => true,
            'published_at' => null,
        ]);

        $results = $this->builder()->published()->get();

        $this->assertFalse($results->contains('id', $draft->id));
    }

    public function test_published_excludes_non_public_entries(): void
    {
        $private = Entry::factory()->create([
            'status_is_public' => false,
            'published_at' => now()->subHour(),
        ]);

        $results = $this->builder()->published()->get();

        $this->assertFalse($results->contains('id', $private->id));
    }

    // -------------------------------------------------------------------------
    // withStatus()
    // -------------------------------------------------------------------------

    public function test_with_status_returns_matching_entries(): void
    {
        $active = Entry::factory()->create(['status_handle' => 'active']);
        Entry::factory()->create(['status_handle' => 'draft']);

        $results = $this->builder()->withStatus('active')->get();

        $this->assertTrue($results->contains('id', $active->id));
        $this->assertCount(1, $results);
    }

    public function test_with_status_excludes_non_matching_entries(): void
    {
        $draft = Entry::factory()->create(['status_handle' => 'draft']);

        $results = $this->builder()->withStatus('published')->get();

        $this->assertFalse($results->contains('id', $draft->id));
    }

    // -------------------------------------------------------------------------
    // withAuthor()
    // -------------------------------------------------------------------------

    public function test_with_author_returns_entries_with_given_author(): void
    {
        $user = User::factory()->create();
        $ea = EntryAuthor::factory()->create(['user_id' => $user->id]);
        $entry = Entry::factory()->create();
        $entry->authors()->attach($ea->id, ['sort_order' => 0]);

        $other = Entry::factory()->create();

        $results = $this->builder()->withAuthor($user->id)->get();

        $this->assertTrue($results->contains('id', $entry->id));
        $this->assertFalse($results->contains('id', $other->id));
    }

    public function test_with_author_excludes_entries_without_given_author(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $ea1 = EntryAuthor::factory()->create(['user_id' => $user1->id]);
        $entry = Entry::factory()->create();
        $entry->authors()->attach($ea1->id, ['sort_order' => 0]);

        $results = $this->builder()->withAuthor($user2->id)->get();

        $this->assertFalse($results->contains('id', $entry->id));
    }

    public function test_with_author_returns_empty_collection_when_no_entries_have_that_author(): void
    {
        Entry::factory()->create();

        $results = $this->builder()->withAuthor(99999)->get();

        $this->assertEmpty($results);
    }

    // -------------------------------------------------------------------------
    // withCategory()
    // -------------------------------------------------------------------------

    public function test_with_category_returns_entries_with_given_category(): void
    {
        $category = Category::factory()->create();
        $entry = Entry::factory()->create();
        $entry->categories()->attach($category->id);

        $other = Entry::factory()->create();

        $results = $this->builder()->withCategory($category->id)->get();

        $this->assertTrue($results->contains('id', $entry->id));
        $this->assertFalse($results->contains('id', $other->id));
    }

    public function test_with_category_excludes_entries_without_given_category(): void
    {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();
        $entry = Entry::factory()->create();
        $entry->categories()->attach($category1->id);

        $results = $this->builder()->withCategory($category2->id)->get();

        $this->assertFalse($results->contains('id', $entry->id));
    }

    public function test_with_category_returns_empty_collection_when_no_entries_have_that_category(): void
    {
        Entry::factory()->create();

        $results = $this->builder()->withCategory(99999)->get();

        $this->assertEmpty($results);
    }

    // -------------------------------------------------------------------------
    // where()
    // -------------------------------------------------------------------------

    public function test_where_filters_by_column_and_value(): void
    {
        $match = Entry::factory()->create(['status_handle' => 'live']);
        Entry::factory()->create(['status_handle' => 'draft']);

        $results = $this->builder()->where('status_handle', 'live')->get();

        $this->assertTrue($results->contains('id', $match->id));
        $this->assertCount(1, $results);
    }

    public function test_where_supports_explicit_operator(): void
    {
        $match = Entry::factory()->create(['title' => 'Alpha']);
        Entry::factory()->create(['title' => 'Beta']);

        $results = $this->builder()->where('title', '=', 'Alpha')->get();

        $this->assertTrue($results->contains('id', $match->id));
        $this->assertCount(1, $results);
    }

    public function test_where_with_not_equal_operator_excludes_matching_row(): void
    {
        $entry = Entry::factory()->create(['status_handle' => 'draft']);

        $results = $this->builder()->where('status_handle', '!=', 'draft')->get();

        $this->assertFalse($results->contains('id', $entry->id));
    }

    // -------------------------------------------------------------------------
    // orderBy() and latest()
    // -------------------------------------------------------------------------

    public function test_order_by_ascending_returns_entries_in_correct_order(): void
    {
        $second = Entry::factory()->create(['title' => 'Beta']);
        $first = Entry::factory()->create(['title' => 'Alpha']);

        $results = $this->builder()->orderBy('title', 'asc')->get();

        $this->assertEquals($first->id, $results->first()->id);
        $this->assertEquals($second->id, $results->last()->id);
    }

    public function test_order_by_defaults_to_ascending(): void
    {
        $second = Entry::factory()->create(['title' => 'Beta']);
        $first = Entry::factory()->create(['title' => 'Alpha']);

        $results = $this->builder()->orderBy('title')->get();

        $this->assertEquals($first->id, $results->first()->id);
        $this->assertEquals($second->id, $results->last()->id);
    }

    public function test_order_by_descending_returns_entries_in_correct_order(): void
    {
        $first = Entry::factory()->create(['title' => 'Alpha']);
        $second = Entry::factory()->create(['title' => 'Beta']);

        $results = $this->builder()->orderBy('title', 'desc')->get();

        $this->assertEquals($second->id, $results->first()->id);
        $this->assertEquals($first->id, $results->last()->id);
    }

    public function test_latest_orders_by_created_at_descending(): void
    {
        $older = Entry::factory()->create();
        $newer = Entry::factory()->create();

        // Force deterministic timestamps via the query builder (bypasses $fillable)
        Entry::where('id', $older->id)->update(['created_at' => now()->subDay()]);
        Entry::where('id', $newer->id)->update(['created_at' => now()]);

        $results = $this->builder()->latest()->get();

        $this->assertEquals($newer->id, $results->first()->id);
        $this->assertEquals($older->id, $results->last()->id);
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    public function test_get_returns_a_collection(): void
    {
        Entry::factory()->count(3)->create();

        $results = $this->builder()->get();

        $this->assertInstanceOf(Collection::class, $results);
    }

    public function test_get_returns_all_entries_when_no_filters_applied(): void
    {
        Entry::factory()->count(3)->create();

        $results = $this->builder()->get();

        $this->assertCount(3, $results);
    }

    public function test_get_returns_empty_collection_when_no_entries_exist(): void
    {
        $results = $this->builder()->get();

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertEmpty($results);
    }

    public function test_get_eager_loads_entry_group_relation(): void
    {
        $group = EntryGroup::factory()->create();
        Entry::factory()->for($group)->create();

        $results = $this->builder()->inGroup($group)->get();

        $this->assertTrue($results->first()->relationLoaded('entryGroup'));
    }

    public function test_get_eager_loads_entry_type_relation(): void
    {
        $group = EntryGroup::factory()->create();
        $type = EntryType::factory()->for($group)->create();
        Entry::factory()->for($group)->for($type)->create();

        $results = $this->builder()->inGroup($group)->get();

        $this->assertTrue($results->first()->relationLoaded('entryType'));
    }

    public function test_get_eager_loads_authors_relation(): void
    {
        Entry::factory()->create();

        $results = $this->builder()->get();

        $this->assertTrue($results->first()->relationLoaded('authors'));
    }

    public function test_get_eager_loads_categories_relation(): void
    {
        Entry::factory()->create();

        $results = $this->builder()->get();

        $this->assertTrue($results->first()->relationLoaded('categories'));
    }

    // -------------------------------------------------------------------------
    // paginate()
    // -------------------------------------------------------------------------

    public function test_paginate_returns_length_aware_paginator(): void
    {
        Entry::factory()->count(5)->create();

        $paginator = $this->builder()->paginate();

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
    }

    public function test_paginate_respects_per_page_argument(): void
    {
        Entry::factory()->count(10)->create();

        $paginator = $this->builder()->paginate(3);

        $this->assertEquals(3, $paginator->perPage());
        $this->assertCount(3, $paginator->items());
    }

    public function test_paginate_uses_default_per_page_of_fifteen(): void
    {
        Entry::factory()->count(20)->create();

        $paginator = $this->builder()->paginate();

        $this->assertEquals(15, $paginator->perPage());
    }

    public function test_paginate_reports_correct_total(): void
    {
        Entry::factory()->count(7)->create();

        $paginator = $this->builder()->paginate(3);

        $this->assertEquals(7, $paginator->total());
    }

    // -------------------------------------------------------------------------
    // first()
    // -------------------------------------------------------------------------

    public function test_first_returns_entry_model_when_match_found(): void
    {
        $entry = Entry::factory()->create();

        $result = $this->builder()->where('id', $entry->id)->first();

        $this->assertInstanceOf(Entry::class, $result);
        $this->assertEquals($entry->id, $result->id);
    }

    public function test_first_returns_null_when_no_match(): void
    {
        $result = $this->builder()->where('id', 99999)->first();

        $this->assertNull($result);
    }

    public function test_first_eager_loads_relations(): void
    {
        Entry::factory()->create();

        $result = $this->builder()->first();

        $this->assertNotNull($result);
        $this->assertTrue($result->relationLoaded('entryGroup'));
        $this->assertTrue($result->relationLoaded('authors'));
        $this->assertTrue($result->relationLoaded('categories'));
    }

    // -------------------------------------------------------------------------
    // firstOrFail()
    // -------------------------------------------------------------------------

    public function test_first_or_fail_returns_entry_model_when_match_found(): void
    {
        $entry = Entry::factory()->create();

        $result = $this->builder()->where('id', $entry->id)->firstOrFail();

        $this->assertInstanceOf(Entry::class, $result);
        $this->assertEquals($entry->id, $result->id);
    }

    public function test_first_or_fail_throws_model_not_found_exception_when_no_match(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->builder()->where('id', 99999)->firstOrFail();
    }

    public function test_first_or_fail_eager_loads_relations(): void
    {
        Entry::factory()->create();

        $result = $this->builder()->firstOrFail();

        $this->assertTrue($result->relationLoaded('entryGroup'));
        $this->assertTrue($result->relationLoaded('authors'));
        $this->assertTrue($result->relationLoaded('categories'));
    }

    // -------------------------------------------------------------------------
    // count()
    // -------------------------------------------------------------------------

    public function test_count_returns_zero_when_no_entries_exist(): void
    {
        $this->assertEquals(0, $this->builder()->count());
    }

    public function test_count_returns_correct_total(): void
    {
        Entry::factory()->count(4)->create();

        $this->assertEquals(4, $this->builder()->count());
    }

    public function test_count_respects_applied_filters(): void
    {
        Entry::factory()->count(3)->create(['status_handle' => 'active']);
        Entry::factory()->count(2)->create(['status_handle' => 'draft']);

        $this->assertEquals(3, $this->builder()->withStatus('active')->count());
    }

    // -------------------------------------------------------------------------
    // Method chaining
    // -------------------------------------------------------------------------

    public function test_multiple_filters_can_be_chained_together(): void
    {
        $group = EntryGroup::factory()->create();
        $type = EntryType::factory()->for($group)->create();

        $match = Entry::factory()->for($group)->for($type)->create([
            'status_handle' => 'active',
            'status_is_public' => true,
            'published_at' => now()->subHour(),
        ]);

        // Same group + type, but not published
        Entry::factory()->for($group)->for($type)->create([
            'status_handle' => 'draft',
            'status_is_public' => false,
            'published_at' => null,
        ]);

        // Different group
        Entry::factory()->create([
            'status_handle' => 'active',
            'status_is_public' => true,
            'published_at' => now()->subHour(),
        ]);

        $results = $this->builder()
            ->inGroup($group)
            ->ofType($type)
            ->published()
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($match->id, $results->first()->id);
    }

    public function test_chaining_status_and_author_filters(): void
    {
        $user = User::factory()->create();
        $ea = EntryAuthor::factory()->create(['user_id' => $user->id]);

        $match = Entry::factory()->create(['status_handle' => 'published']);
        $match->authors()->attach($ea->id, ['sort_order' => 0]);

        // Right status, wrong author
        Entry::factory()->create(['status_handle' => 'published']);

        // Right author, wrong status
        $wrongStatus = Entry::factory()->create(['status_handle' => 'draft']);
        $wrongStatus->authors()->attach($ea->id, ['sort_order' => 0]);

        $results = $this->builder()
            ->withStatus('published')
            ->withAuthor($user->id)
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($match->id, $results->first()->id);
    }

    // -------------------------------------------------------------------------
    // whereField()
    // -------------------------------------------------------------------------

    public function test_where_field_returns_self(): void
    {
        [$entry, $field] = $this->makeEntryWithTextField('slug');
        $this->storeTextValue($entry, $field, 'hello');

        $builder = $this->builder();
        $this->assertSame($builder, $builder->whereField('slug', 'hello'));
    }

    public function test_where_field_implicit_equals_returns_matching_entry(): void
    {
        // Both entries share the same field and group — only their stored values differ.
        [$match, $field] = $this->makeEntryWithTextField('slug');
        $this->storeTextValue($match, $field, 'my-post');

        $other = Entry::factory()->create([
            'entry_group_id' => $match->entry_group_id,
            'entry_type_id'  => $match->entry_type_id,
        ]);
        $this->storeTextValue($other, $field, 'another-post');

        $results = $this->builder()->whereField('slug', 'my-post')->get();

        $this->assertTrue($results->contains('id', $match->id));
        $this->assertFalse($results->contains('id', $other->id));
    }

    public function test_where_field_explicit_equals_operator_returns_matching_entry(): void
    {
        // Both entries share the same field and group — only their stored values differ.
        [$match, $field] = $this->makeEntryWithTextField('colour');
        $this->storeTextValue($match, $field, 'blue');

        $other = Entry::factory()->create([
            'entry_group_id' => $match->entry_group_id,
            'entry_type_id'  => $match->entry_type_id,
        ]);
        $this->storeTextValue($other, $field, 'red');

        $results = $this->builder()->whereField('colour', '=', 'blue')->get();

        $this->assertTrue($results->contains('id', $match->id));
        $this->assertFalse($results->contains('id', $other->id));
    }

    public function test_where_field_not_equal_operator_excludes_matching_entry(): void
    {
        [$entry, $field] = $this->makeEntryWithTextField('status_label');
        $this->storeTextValue($entry, $field, 'draft');

        $results = $this->builder()->whereField('status_label', '!=', 'draft')->get();

        $this->assertFalse($results->contains('id', $entry->id));
    }

    public function test_where_field_excludes_entries_without_the_field_value(): void
    {
        // Entry has the field in its layout but no stored value row.
        [$entry] = $this->makeEntryWithTextField('teaser');

        $results = $this->builder()->whereField('teaser', 'anything')->get();

        $this->assertFalse($results->contains('id', $entry->id));
    }

    public function test_where_field_can_be_chained_with_other_filters(): void
    {
        $fieldType = Type::firstOrCreate(
            ['object' => Text::class],
            ['name' => 'Text', 'settings' => []]
        );
        $field     = Field::factory()->create(['field_type_id' => $fieldType->id, 'handle' => 'region']);
        $layout    = FieldLayout::factory()->create();
        $tab       = Tab::factory()->create(['field_layout_id' => $layout->id]);
        TabElement::factory()->create(['field_layout_tab_id' => $tab->id, 'field_id' => $field->id]);

        $group = EntryGroup::factory()->create(['field_layout_id' => $layout->id]);
        $type  = EntryType::factory()->create(['entry_group_id' => $group->id, 'field_layout_id' => null]);

        $match = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id'  => $type->id,
            'status_handle'  => 'published',
        ]);
        $this->storeTextValue($match, $field, 'europe');

        // Same group and field value, wrong status
        $wrongStatus = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id'  => $type->id,
            'status_handle'  => 'draft',
        ]);
        $this->storeTextValue($wrongStatus, $field, 'europe');

        // Same group and status, wrong field value
        $wrongField = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id'  => $type->id,
            'status_handle'  => 'published',
        ]);
        $this->storeTextValue($wrongField, $field, 'asia');

        $results = $this->builder()
            ->inGroup($group)
            ->withStatus('published')
            ->whereField('region', 'europe')
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($match->id, $results->first()->id);
    }

    public function test_where_field_throws_for_unknown_handle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no field with handle \[does-not-exist\]/');

        $this->builder()->whereField('does-not-exist', 'value');
    }

    public function test_where_field_throws_for_relational_field_handle(): void
    {
        $fieldType = Type::factory()->create(['object' => Relationship::class]);
        Field::factory()->create(['field_type_id' => $fieldType->id, 'handle' => 'related-items']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/\[related-items\] is a relational field/');

        $this->builder()->whereField('related-items', 1);
    }
}
