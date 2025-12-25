<?php

namespace Tests\Unit\Models;

use App\Models\BbValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BbValueTest extends TestCase
{
    use RefreshDatabase;

    public function test_bb_value_has_fillable_attributes(): void
    {
        $bbValue = new BbValue();
        $fillable = [
            'field_value',
            'ip_address',
            'field_name'
        ];
        $this->assertEquals($fillable, $bbValue->getFillable());
    }

    public function test_bb_value_has_correct_table_name(): void
    {
        $bbValue = new BbValue();
        $this->assertEquals('bb_values', $bbValue->getTable());
    }

    public function test_bb_value_can_be_created_via_factory(): void
    {
        $bbValue = BbValue::factory()->create([
            'field_name' => 'test_field',
            'field_value' => 'test_value',
            'ip_address' => '127.0.0.1',
        ]);

        $this->assertDatabaseHas('bb_values', [
            'field_name' => 'test_field',
            'field_value' => 'test_value',
            'ip_address' => '127.0.0.1',
        ]);

        $this->assertEquals('test_field', $bbValue->field_name);
        $this->assertEquals('test_value', $bbValue->field_value);
        $this->assertEquals('127.0.0.1', $bbValue->ip_address);
    }
}
