<?php

namespace Tests\Unit\Services;

use App\Field\Types\Text;
use App\Models\Entry;
use App\Models\EntryGroup;
use App\Models\EntryType;
use App\Models\Field;
use App\Models\Field\Type;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;
use App\Models\FieldValue;
use App\Services\EntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryServiceFieldValueTest extends TestCase
{
    use RefreshDatabase;

    private EntryService $service;

    public function test_get_field_value_returns_null_when_no_value_stored(): void
    {
        [$entry] = $this->makeEntryWithTextField('summary');

        $result = $this->service->getFieldValue($entry, 'summary');

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a complete entry wired to a field layout that contains one Text field.
     * Returns [$entry, $field] so tests can make assertions against each.
     */
    private function makeEntryWithTextField(string $handle = 'body'): array
    {
        $fieldType = Type::factory()->create(['object' => Text::class]);
        $field = Field::factory()->create(['field_type_id' => $fieldType->id, 'handle' => $handle]);

        $layout = FieldLayout::factory()->create();
        $tab = Tab::factory()->create(['field_layout_id' => $layout->id]);
        TabElement::factory()->create(['field_layout_tab_id' => $tab->id, 'field_id' => $field->id]);

        $group = EntryGroup::factory()->create(['field_layout_id' => $layout->id]);
        $type = EntryType::factory()->create(['entry_group_id' => $group->id, 'field_layout_id' => null]);
        $entry = Entry::factory()->create(['entry_group_id' => $group->id, 'entry_type_id' => $type->id]);

        return [$entry, $field];
    }

    // -------------------------------------------------------------------------
    // getFieldValue()
    // -------------------------------------------------------------------------

    public function test_get_field_value_returns_stored_scalar_value(): void
    {
        [$entry, $field] = $this->makeEntryWithTextField('headline');

        FieldValue::create([
            'field_id' => $field->id,
            'fieldable_id' => $entry->id,
            'fieldable_type' => $entry->getMorphClass(),
            'value_text' => 'Breaking News',
        ]);

        $result = $this->service->getFieldValue($entry, 'headline');

        $this->assertSame('Breaking News', $result);
    }

    public function test_get_field_value_loads_missing_relations_automatically(): void
    {
        // Load a bare entry (no relations) and confirm getFieldValue still works
        [$entry, $field] = $this->makeEntryWithTextField('intro');

        $bare = Entry::find($entry->id); // no eager-loads at all

        FieldValue::create([
            'field_id' => $field->id,
            'fieldable_id' => $bare->id,
            'fieldable_type' => $bare->getMorphClass(),
            'value_text' => 'Hello intro',
        ]);

        $result = $this->service->getFieldValue($bare, 'intro');

        $this->assertSame('Hello intro', $result);
    }

    public function test_get_field_value_returns_null_for_unknown_handle(): void
    {
        [$entry] = $this->makeEntryWithTextField('body');

        $result = $this->service->getFieldValue($entry, 'nonexistent_handle');

        $this->assertNull($result);
    }

    public function test_set_field_value_persists_a_scalar_value(): void
    {
        [$entry, $field] = $this->makeEntryWithTextField('content');

        $this->service->setFieldValue($entry, 'content', 'Hello World');

        $this->assertDatabaseHas('field_values', [
            'field_id' => $field->id,
            'fieldable_id' => $entry->id,
            'fieldable_type' => $entry->getMorphClass(),
            'value_text' => 'Hello World',
        ]);
    }

    // -------------------------------------------------------------------------
    // setFieldValue()
    // -------------------------------------------------------------------------

    public function test_set_field_value_overwrites_existing_value(): void
    {
        [$entry, $field] = $this->makeEntryWithTextField('teaser');

        $this->service->setFieldValue($entry, 'teaser', 'First value');
        $this->service->setFieldValue($entry, 'teaser', 'Updated value');

        $this->assertDatabaseHas('field_values', [
            'field_id' => $field->id,
            'fieldable_id' => $entry->id,
            'fieldable_type' => $entry->getMorphClass(),
            'value_text' => 'Updated value',
        ]);

        // Only one row should exist — not two
        $count = FieldValue::where('field_id', $field->id)
            ->where('fieldable_id', $entry->id)
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_set_field_value_silently_skips_unknown_handle(): void
    {
        [$entry] = $this->makeEntryWithTextField('body');

        // Should not throw; unknown handles are silently ignored
        $this->service->setFieldValue($entry, 'this_does_not_exist', 'value');

        $this->assertDatabaseCount('field_values', 0);
    }

    public function test_get_after_set_returns_correct_value(): void
    {
        [$entry] = $this->makeEntryWithTextField('blurb');

        $this->service->setFieldValue($entry, 'blurb', 'Round-trip value');

        $result = $this->service->getFieldValue($entry, 'blurb');

        $this->assertSame('Round-trip value', $result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EntryService::class);
    }
}
