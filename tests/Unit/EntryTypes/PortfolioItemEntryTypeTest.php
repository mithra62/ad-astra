<?php

namespace Tests\Unit\EntryTypes;

use App\EntryTypes\PortfolioItemEntryType;
use App\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioItemEntryTypeTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): PortfolioItemEntryType
    {
        $record = EntryType::factory()->create(['class' => PortfolioItemEntryType::class]);
        return new PortfolioItemEntryType($record);
    }

    public function test_before_create_defaults_published_at_to_now(): void
    {
        $type = $this->makeType();

        $result = $type->beforeCreate([]);

        $this->assertNotNull($result['published_at']);
    }

    public function test_before_create_respects_explicit_published_at(): void
    {
        $type = $this->makeType();
        $date = now()->addWeek()->toDateTimeString();

        $result = $type->beforeCreate(['published_at' => $date]);

        $this->assertSame($date, $result['published_at']);
    }
}
