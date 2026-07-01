<?php

namespace Tests\Unit\EntryTypes;

use AdAstra\EntryTypes\PageEntryType;
use AdAstra\Models\EntryBehavior;
use AdAstra\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageEntryTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_before_create_returns_data_unchanged(): void
    {
        $type = $this->makeType();
        $data = ['title' => 'About Us'];

        $result = $type->beforeCreate($data);

        $this->assertSame($data, $result);
    }

    private function makeType(): PageEntryType
    {
        $record = EntryType::factory()->create(['entry_behavior_id' => EntryBehavior::where('handle', 'page')->value('id')]);
        return new PageEntryType($record);
    }
}
