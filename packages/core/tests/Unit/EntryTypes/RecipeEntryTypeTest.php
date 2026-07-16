<?php

namespace Tests\Unit\EntryTypes;

use AdAstra\EntryTypes\RecipeEntryType;
use AdAstra\Models\Entry;
use AdAstra\Models\EntryBehavior;
use AdAstra\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeEntryTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_before_create_computes_total_time_from_prep_and_cook(): void
    {
        $type = $this->makeType();

        $result = $type->beforeCreate([
            'fields' => ['prep_time' => 15, 'cook_time' => 30],
        ]);

        $this->assertSame(45, $result['fields']['total_time']);
    }

    // -------------------------------------------------------------------------
    // beforeCreate — total_time computation
    // -------------------------------------------------------------------------

    private function makeType(): RecipeEntryType
    {
        $record = EntryType::factory()->create(['entry_behavior_id' => EntryBehavior::where('handle', 'recipe')->value('id')]);
        return new RecipeEntryType($record);
    }

    public function test_before_create_computes_total_time_when_only_prep_time_provided(): void
    {
        $type = $this->makeType();

        $result = $type->beforeCreate([
            'fields' => ['prep_time' => 20],
        ]);

        $this->assertSame(20, $result['fields']['total_time']);
    }

    public function test_before_create_computes_total_time_when_only_cook_time_provided(): void
    {
        $type = $this->makeType();

        $result = $type->beforeCreate([
            'fields' => ['cook_time' => 45],
        ]);

        $this->assertSame(45, $result['fields']['total_time']);
    }

    public function test_before_create_does_not_inject_total_time_when_both_absent(): void
    {
        $type = $this->makeType();

        $result = $type->beforeCreate(['fields' => ['servings' => 4]]);

        $this->assertArrayNotHasKey('total_time', $result['fields']);
    }

    // -------------------------------------------------------------------------
    // beforeUpdate — total_time computation
    // -------------------------------------------------------------------------

    public function test_before_update_recomputes_total_time_when_prep_time_changes(): void
    {
        $type = $this->makeType();
        $entry = Entry::factory()->create();

        $result = $type->beforeUpdate($entry, [
            'fields' => ['prep_time' => 10, 'cook_time' => 25],
        ]);

        $this->assertSame(35, $result['fields']['total_time']);
    }

    public function test_before_update_does_not_inject_total_time_when_neither_time_field_present(): void
    {
        $type = $this->makeType();
        $entry = Entry::factory()->create();

        $result = $type->beforeUpdate($entry, ['fields' => ['servings' => 6]]);

        $this->assertArrayNotHasKey('total_time', $result['fields']);
    }
}
