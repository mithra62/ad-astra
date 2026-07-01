<?php

namespace Tests\Unit\Field\Types;

use AdAstra\Field\AbstractField;
use AdAstra\Field\Types\Relationship;
use AdAstra\Models\Entry;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\Field;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelationshipTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Identity
    // -------------------------------------------------------------------------

    public function test_extends_abstract_field(): void
    {
        $this->assertInstanceOf(AbstractField::class, new Relationship([]));
    }

    public function test_handle_returns_relationship(): void
    {
        $this->assertEquals('relationship', (new Relationship([]))->handle());
    }

    public function test_name_returns_relationship(): void
    {
        $this->assertEquals('Relationship', (new Relationship([]))->name());
    }

    // -------------------------------------------------------------------------
    // Storage
    // -------------------------------------------------------------------------

    public function test_storage_column_returns_value_json(): void
    {
        $this->assertEquals('value_json', (new Relationship([]))->storageColumn());
    }

    public function test_is_relational_returns_true(): void
    {
        $this->assertTrue((new Relationship([]))->isRelational());
    }

    // -------------------------------------------------------------------------
    // Rules
    // -------------------------------------------------------------------------

    public function test_get_rules_contains_array(): void
    {
        $this->assertContains('array', (new Relationship([]))->getRules());
    }

    // -------------------------------------------------------------------------
    // getSetting
    // -------------------------------------------------------------------------

    public function test_get_setting_returns_configured_limit(): void
    {
        $field = new Relationship(['limit' => 5]);

        $this->assertEquals(5, $field->getSetting('limit'));
    }

    public function test_get_setting_returns_configured_entry_group(): void
    {
        $field = new Relationship(['entry_groups' => 'blog-posts']);

        $this->assertEquals('blog-posts', $field->getSetting('entry_groups'));
    }

    public function test_get_setting_returns_default_when_key_missing(): void
    {
        $field = new Relationship([]);

        $this->assertNull($field->getSetting('limit'));
        $this->assertEquals(0, $field->getSetting('limit', 0));
    }

    // -------------------------------------------------------------------------
    // validate()
    // -------------------------------------------------------------------------

    public function test_validate_passes_for_null(): void
    {
        $this->assertTrue((new Relationship([]))->validate(null));
    }

    public function test_validate_passes_for_empty_array(): void
    {
        $this->assertTrue((new Relationship([]))->validate([]));
    }

    public function test_validate_passes_for_valid_array_of_ids(): void
    {
        $this->assertTrue((new Relationship([]))->validate([1, 2, 3]));
    }

    public function test_validate_fails_for_non_array_string(): void
    {
        $result = (new Relationship([]))->validate('not-an-array');

        $this->assertIsString($result);
        $this->assertStringContainsString('array of entry IDs', $result);
    }

    public function test_validate_fails_for_non_array_integer(): void
    {
        $result = (new Relationship([]))->validate(42);

        $this->assertIsString($result);
        $this->assertStringContainsString('array of entry IDs', $result);
    }

    public function test_validate_passes_when_count_equals_limit(): void
    {
        $field = new Relationship(['limit' => 3]);

        $this->assertTrue($field->validate([1, 2, 3]));
    }

    public function test_validate_fails_when_count_exceeds_limit(): void
    {
        $field = new Relationship(['limit' => 2]);

        $result = $field->validate([1, 2, 3]);

        $this->assertIsString($result);
        $this->assertStringContainsString('2', $result);
        $this->assertStringContainsString('related entries', $result);
    }

    public function test_validate_ignores_limit_when_limit_is_zero(): void
    {
        $field = new Relationship(['limit' => 0]);

        $this->assertTrue($field->validate([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]));
    }

    public function test_validate_ignores_limit_when_limit_is_null(): void
    {
        $field = new Relationship([]);

        $this->assertTrue($field->validate([1, 2, 3, 4, 5]));
    }

    // -------------------------------------------------------------------------
    // render() — output structure
    // -------------------------------------------------------------------------

    public function test_render_returns_a_string(): void
    {
        $html = (new Relationship([]))->render(['id' => 'f1', 'value' => null]);

        $this->assertIsString($html);
        $this->assertNotEmpty($html);
    }

    public function test_render_includes_select_element_with_correct_name(): void
    {
        $field = $this->makeFieldModel();
        $html = (new Relationship([]))->render(['id' => 'f1', 'value' => null, 'field' => $field]);

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('name="fields[' . $field->handle . '][]"', $html);
        $this->assertStringContainsString('multiple', $html);
    }

    /**
     * Return a minimal Field model with a random handle so render() can build
     * the correct name="fields[handle][]" attribute.
     */
    private function makeFieldModel(): Field
    {
        return Field::factory()->make(['handle' => 'related_entries']);
    }

    public function test_render_shows_no_entries_available_when_no_entry_group_configured(): void
    {
        $field = $this->makeFieldModel();
        $html = (new Relationship([]))->render(['id' => 'f1', 'value' => null, 'field' => $field]);

        $this->assertStringContainsString('No entries available', $html);
    }

    public function test_render_shows_entry_titles_when_entry_group_is_configured(): void
    {
        $group = $this->makeEntryGroup('articles');
        $entryA = Entry::factory()->for($group)->create(['title' => 'Alpha Post']);
        $entryB = Entry::factory()->for($group)->create(['title' => 'Beta Post']);

        $field = $this->makeFieldModel();
        $html = (new Relationship(['entry_groups' => 'articles']))
            ->render(['id' => 'f1', 'value' => null, 'field' => $field]);

        $this->assertStringContainsString('Alpha Post', $html);
        $this->assertStringContainsString('Beta Post', $html);
        $this->assertStringContainsString((string)$entryA->id, $html);
        $this->assertStringContainsString((string)$entryB->id, $html);
    }

    /**
     * Create a minimal EntryGroup with the given handle (and a StatusGroup so
     * the factory chain doesn't fail on the status_group_id FK).
     */
    private function makeEntryGroup(string $handle): EntryGroup
    {
        return EntryGroup::factory()->create(['handle' => $handle]);
    }

    public function test_render_excludes_entries_from_other_groups(): void
    {
        $group1 = $this->makeEntryGroup('articles');
        $group2 = $this->makeEntryGroup('pages');
        Entry::factory()->for($group1)->create(['title' => 'Article One']);
        Entry::factory()->for($group2)->create(['title' => 'Page One']);

        $field = $this->makeFieldModel();
        $html = (new Relationship(['entry_groups' => 'articles']))
            ->render(['id' => 'f1', 'value' => null, 'field' => $field]);

        $this->assertStringContainsString('Article One', $html);
        $this->assertStringNotContainsString('Page One', $html);
    }

    public function test_render_accepts_multiple_entry_group_handles(): void
    {
        $group1 = $this->makeEntryGroup('articles');
        $group2 = $this->makeEntryGroup('pages');
        Entry::factory()->for($group1)->create(['title' => 'Article One']);
        Entry::factory()->for($group2)->create(['title' => 'Page One']);

        $field = $this->makeFieldModel();
        $html = (new Relationship(['entry_groups' => ['articles', 'pages']]))
            ->render(['id' => 'f1', 'value' => null, 'field' => $field]);

        $this->assertStringContainsString('Article One', $html);
        $this->assertStringContainsString('Page One', $html);
    }

    public function test_render_marks_currently_related_entries_as_selected(): void
    {
        $group = $this->makeEntryGroup('articles');
        $entry = Entry::factory()->for($group)->create(['title' => 'Selected Post']);

        $field = $this->makeFieldModel();
        $html = (new Relationship(['entry_groups' => 'articles']))
            ->render([
                'id' => 'f1',
                'value' => collect([$entry]),   // as returned by Entry::field()
                'field' => $field,
            ]);

        $this->assertMatchesRegularExpression(
            '/value="' . $entry->id . '"[^>]*selected/',
            $html
        );
    }

    public function test_render_marks_selected_ids_passed_as_plain_array(): void
    {
        $group = $this->makeEntryGroup('articles');
        $entry = Entry::factory()->for($group)->create(['title' => 'Flash Post']);

        $field = $this->makeFieldModel();
        $html = (new Relationship(['entry_groups' => 'articles']))
            ->render([
                'id' => 'f1',
                'value' => [$entry->id],   // as returned by old() flash data
                'field' => $field,
            ]);

        $this->assertMatchesRegularExpression(
            '/value="' . $entry->id . '"[^>]*selected/',
            $html
        );
    }

    public function test_render_does_not_mark_unrelated_entries_as_selected(): void
    {
        $group = $this->makeEntryGroup('articles');
        $selected = Entry::factory()->for($group)->create(['title' => 'Selected']);
        $other = Entry::factory()->for($group)->create(['title' => 'Other']);

        $field = $this->makeFieldModel();
        $html = (new Relationship(['entry_groups' => 'articles']))
            ->render([
                'id' => 'f1',
                'value' => collect([$selected]),
                'field' => $field,
            ]);

        // The "Other" option must not carry the selected attribute.
        $this->assertDoesNotMatchRegularExpression(
            '/value="' . $other->id . '"[^>]*selected/',
            $html
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function test_render_shows_limit_hint_when_limit_is_set(): void
    {
        $field = $this->makeFieldModel();
        $html = (new Relationship(['limit' => 3]))
            ->render(['id' => 'f1', 'value' => null, 'field' => $field]);

        $this->assertStringContainsString('3', $html);
        $this->assertStringContainsString('data-limit="3"', $html);
    }

    public function test_render_omits_limit_hint_when_limit_is_zero(): void
    {
        $field = $this->makeFieldModel();
        $html = (new Relationship(['limit' => 0]))
            ->render(['id' => 'f1', 'value' => null, 'field' => $field]);

        $this->assertStringNotContainsString('data-limit', $html);
    }
}
