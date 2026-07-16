<?php

namespace Tests\Unit\Traits;

use AdAstra\Field\Types\Relationship;
use AdAstra\Field\Types\Text;
use AdAstra\Models\Entry;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryType;
use AdAstra\Models\Field;
use AdAstra\Models\Field\Type as FieldType;
use AdAstra\Models\FieldLayout;
use AdAstra\Models\FieldLayout\Tab;
use AdAstra\Models\FieldLayout\TabElement;
use AdAstra\Models\FieldValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the schema-complete fieldArray() behaviour on the Fieldable trait.
 *
 * fieldArray() should return every non-relational handle from the model's field
 * layout — unset fields resolving to null — while models with no layout keep the
 * legacy stored-only output. Entry is used as the concrete Fieldable model; the
 * behaviour is inherited identically by User, Category, and Media.
 */
class FieldableSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a field of the given type object and attach it to a layout tab.
     */
    private function attachField(FieldLayout $layout, Tab $tab, string $handle, string $typeObject, int $sort): Field
    {
        $type = FieldType::firstOrCreate(
            ['object' => $typeObject],
            ['name' => class_basename($typeObject), 'settings' => []]
        );
        $field = Field::factory()->create(['handle' => $handle, 'field_type_id' => $type->id]);

        TabElement::factory()->create([
            'field_layout_tab_id' => $tab->id,
            'field_id' => $field->id,
            'sort_order' => $sort,
        ]);

        return $field;
    }

    /**
     * Create an Entry whose type points at a layout carrying the given fields.
     *
     * @return array{0: Entry, 1: array<string, Field>}
     */
    private function makeEntryWithSchema(): array
    {
        $layout = FieldLayout::factory()->create();
        $tab = Tab::factory()->create(['field_layout_id' => $layout->id]);

        $fields = [
            'first_name' => $this->attachField($layout, $tab, 'first_name', Text::class, 0),
            'last_name' => $this->attachField($layout, $tab, 'last_name', Text::class, 1),
            'related' => $this->attachField($layout, $tab, 'related', Relationship::class, 2),
        ];

        $group = EntryGroup::factory()->create(['field_layout_id' => null]);
        $type = EntryType::factory()->create([
            'entry_group_id' => $group->id,
            'field_layout_id' => $layout->id,
        ]);
        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
        ]);

        return [$entry, $fields];
    }

    public function test_field_array_includes_unset_schema_fields_as_null(): void
    {
        [$entry, $fields] = $this->makeEntryWithSchema();

        FieldValue::create([
            'fieldable_type' => $entry->getMorphClass(),
            'fieldable_id' => $entry->id,
            'field_id' => $fields['first_name']->id,
            'value_text' => 'Ada',
        ]);

        $result = $entry->fresh()->fieldArray();

        $this->assertArrayHasKey('first_name', $result);
        $this->assertArrayHasKey('last_name', $result);
        $this->assertSame('Ada', $result['first_name']);
        $this->assertNull($result['last_name']);
    }

    public function test_field_array_preserves_layout_order(): void
    {
        [$entry] = $this->makeEntryWithSchema();

        $keys = array_keys($entry->fresh()->fieldArray());

        // first_name (sort 0) precedes last_name (sort 1); relational excluded.
        $this->assertSame(['first_name', 'last_name'], $keys);
    }

    public function test_field_array_excludes_relational_fields(): void
    {
        [$entry] = $this->makeEntryWithSchema();

        $this->assertArrayNotHasKey('related', $entry->fresh()->fieldArray());
    }

    public function test_field_array_returns_stored_only_when_no_layout(): void
    {
        $group = EntryGroup::factory()->create(['field_layout_id' => null]);
        $type = EntryType::factory()->create([
            'entry_group_id' => $group->id,
            'field_layout_id' => null,
        ]);
        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
        ]);

        $this->assertSame([], $entry->fresh()->fieldArray());
    }
}
