<?php

namespace Tests\Unit\Models;

use AdAstra\Models\Entry;
use AdAstra\Models\EntryRelationship;
use AdAstra\Models\Field;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $this->assertEquals(
            ['entry_id', 'related_entry_id', 'field_id', 'sort_order'],
            (new EntryRelationship)->getFillable()
        );
    }

    public function test_entry_relationship_is_belongs_to(): void
    {
        $relationship = EntryRelationship::factory()->create();

        $this->assertInstanceOf(BelongsTo::class, $relationship->entry());
    }

    public function test_entry_relationship_returns_parent_entry(): void
    {
        $entry = Entry::factory()->create();
        $related = Entry::factory()->create();
        $field = Field::factory()->create();
        $rel = EntryRelationship::create([
            'entry_id' => $entry->id,
            'related_entry_id' => $related->id,
            'field_id' => $field->id,
            'sort_order' => 0,
        ]);

        $this->assertEquals($entry->id, $rel->entry->id);
    }

    public function test_related_entry_relationship_is_belongs_to_entry(): void
    {
        $relationship = EntryRelationship::factory()->create();

        $this->assertInstanceOf(BelongsTo::class, $relationship->relatedEntry());
    }

    public function test_related_entry_uses_related_entry_id_foreign_key(): void
    {
        $entry = Entry::factory()->create();
        $related = Entry::factory()->create();
        $field = Field::factory()->create();
        $rel = EntryRelationship::create([
            'entry_id' => $entry->id,
            'related_entry_id' => $related->id,
            'field_id' => $field->id,
            'sort_order' => 0,
        ]);

        $this->assertEquals($related->id, $rel->relatedEntry->id);
    }

    public function test_field_relationship_is_belongs_to(): void
    {
        $relationship = EntryRelationship::factory()->create();

        $this->assertInstanceOf(BelongsTo::class, $relationship->field());
    }

    public function test_field_relationship_returns_associated_field(): void
    {
        $field = Field::factory()->create();
        $rel = EntryRelationship::factory()->create(['field_id' => $field->id]);

        $this->assertEquals($field->id, $rel->field->id);
    }
}
