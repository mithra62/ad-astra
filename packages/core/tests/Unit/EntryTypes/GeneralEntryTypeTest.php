<?php

namespace Tests\Unit\EntryTypes;

use AdAstra\EntryTypes\GeneralEntryType;
use AdAstra\Models\EntryBehavior;
use AdAstra\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralEntryTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_before_create_returns_data_unchanged(): void
    {
        $type = $this->makeType();
        $data = ['title' => 'Hello', 'fields' => ['body' => 'Content']];

        $result = $type->beforeCreate($data);

        $this->assertSame($data, $result);
    }

    private function makeType(): GeneralEntryType
    {
        $record = EntryType::factory()->create(['entry_behavior_id' => EntryBehavior::where('handle', 'general')->value('id')]);
        return new GeneralEntryType($record);
    }
}
