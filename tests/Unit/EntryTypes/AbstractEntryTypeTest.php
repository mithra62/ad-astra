<?php

namespace Tests\Unit\EntryTypes;

use App\EntryTypes\AbstractEntryType;
use App\Models\Entry;
use App\Models\EntryGroup;
use App\Models\EntryType;
use App\Models\Field;
use App\Models\Field\Type as FieldType;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;
use App\Models\FieldValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbstractEntryTypeTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // validate() — base implementation
    // -------------------------------------------------------------------------

    public function test_validate_returns_empty_array_by_default(): void
    {
        $type = $this->makeAnonymousEntryType();

        $result = $type->validate(['title' => 'Hello']);

        $this->assertSame([], $result);
    }

    public function test_validate_accepts_null_entry_for_create_context(): void
    {
        $type = $this->makeAnonymousEntryType();

        $result = $type->validate(['status' => 'published'], null);

        $this->assertSame([], $result);
    }

    public function test_validate_accepts_entry_for_update_context(): void
    {
        $entry = Entry::factory()->create();
        $type  = $this->makeAnonymousEntryType();

        $result = $type->validate(['title' => 'Updated'], $entry);

        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // existingFieldValue() — safe field reading
    // -------------------------------------------------------------------------

    public function test_existing_field_value_returns_null_when_field_has_no_value(): void
    {
        [$entry] = $this->makeEntryWithTextField('summary');
        $type = $this->makeAnonymousEntryType($entry->entryType);

        $result = $this->callExistingFieldValue($type, $entry, 'summary');

        $this->assertNull($result);
    }

    public function test_existing_field_value_returns_stored_value(): void
    {
        [$entry, $field] = $this->makeEntryWithTextField('headline');

        FieldValue::create([
            'field_id'       => $field->id,
            'fieldable_id'   => $entry->id,
            'fieldable_type' => $entry->getMorphClass(),
            'value_text'     => 'Breaking News',
        ]);

        $type = $this->makeAnonymousEntryType($entry->entryType);

        $result = $this->callExistingFieldValue($type, $entry, 'headline');

        $this->assertSame('Breaking News', $result);
    }

    public function test_existing_field_value_loads_relations_on_bare_entry(): void
    {
        [$entry, $field] = $this->makeEntryWithTextField('intro');

        FieldValue::create([
            'field_id'       => $field->id,
            'fieldable_id'   => $entry->id,
            'fieldable_type' => $entry->getMorphClass(),
            'value_text'     => 'Hello',
        ]);

        // Fetch a bare entry — no eager-loads.
        $bare = Entry::find($entry->id);
        $type = $this->makeAnonymousEntryType($bare->entryType);

        $result = $this->callExistingFieldValue($type, $bare, 'intro');

        $this->assertSame('Hello', $result);
    }

    public function test_existing_field_value_is_idempotent_when_already_loaded(): void
    {
        [$entry, $field] = $this->makeEntryWithTextField('slug_field');

        FieldValue::create([
            'field_id'       => $field->id,
            'fieldable_id'   => $entry->id,
            'fieldable_type' => $entry->getMorphClass(),
            'value_text'     => 'some-slug',
        ]);

        // Pre-load relations.
        $entry->load('fieldValues.field.fieldType');
        $type = $this->makeAnonymousEntryType($entry->entryType);

        // Calling twice should return the same value and not throw.
        $first  = $this->callExistingFieldValue($type, $entry, 'slug_field');
        $second = $this->callExistingFieldValue($type, $entry, 'slug_field');

        $this->assertSame($first, $second);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAnonymousEntryType(?EntryType $record = null): AbstractEntryType
    {
        $record ??= EntryType::factory()->create();

        return new class($record) extends AbstractEntryType {
            // Expose protected method for testing.
            public function callExistingFieldValue(Entry $entry, string $handle): mixed
            {
                return $this->existingFieldValue($entry, $handle);
            }
        };
    }

    private function callExistingFieldValue(AbstractEntryType $type, Entry $entry, string $handle): mixed
    {
        // @phpstan-ignore-next-line
        return $type->callExistingFieldValue($entry, $handle);
    }

    private function makeEntryWithTextField(string $handle = 'body'): array
    {
        $fieldType = FieldType::factory()->create(['object' => \App\Field\Types\Text::class]);
        $field     = Field::factory()->create(['field_type_id' => $fieldType->id, 'handle' => $handle]);

        $layout = FieldLayout::factory()->create();
        $tab    = Tab::factory()->create(['field_layout_id' => $layout->id]);
        TabElement::factory()->create(['field_layout_tab_id' => $tab->id, 'field_id' => $field->id]);

        $group = EntryGroup::factory()->create(['field_layout_id' => $layout->id]);
        $type  = EntryType::factory()->create(['entry_group_id' => $group->id]);
        $entry = Entry::factory()->create(['entry_group_id' => $group->id, 'entry_type_id' => $type->id]);

        return [$entry, $field];
    }
}
