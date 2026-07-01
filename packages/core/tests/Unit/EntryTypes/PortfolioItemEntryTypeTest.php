<?php

namespace Tests\Unit\EntryTypes;

use AdAstra\EntryTypes\PortfolioItemEntryType;
use AdAstra\Models\EntryBehavior;
use AdAstra\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioItemEntryTypeTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): PortfolioItemEntryType
    {
        $record = EntryType::factory()->create(['entry_behavior_id' => EntryBehavior::where('handle', 'portfolio-item')->value('id')]);
        return new PortfolioItemEntryType($record);
    }

    public function test_before_create_returns_data_unchanged(): void
    {
        $type = $this->makeType();
        $data = ['title' => 'Branding Project'];

        $result = $type->beforeCreate($data);

        $this->assertSame($data, $result);
    }
}
