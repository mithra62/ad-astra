<?php

namespace Tests\Unit\EntryTypes;

use App\EntryTypes\GeneralEntryType;
use App\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralEntryTypeTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): GeneralEntryType
    {
        $record = EntryType::factory()->create(['class' => GeneralEntryType::class]);
        return new GeneralEntryType($record);
    }

    public function test_before_create_returns_data_unchanged(): void
    {
        $type = $this->makeType();
        $data = ['title' => 'Hello', 'fields' => ['body' => 'Content']];

        $result = $type->beforeCreate($data);

        $this->assertSame($data, $result);
    }
}
