<?php

namespace Tests\Unit\Models;

use App\Models\BbValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BbValueTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $model = new BbValue;

        $this->assertEquals(['field_value', 'ip_address', 'field_name'], $model->getFillable());
    }

    public function test_uses_bb_values_table(): void
    {
        $model = new BbValue;

        $this->assertEquals('bb_values', $model->getTable());
    }

    public function test_can_create_and_retrieve_a_bb_value(): void
    {
        $bbValue = BbValue::factory()->create([
            'field_name' => 'email',
            'field_value' => 'test@example.com',
            'ip_address' => '127.0.0.1',
        ]);

        $found = BbValue::find($bbValue->id);

        $this->assertEquals('email', $found->field_name);
        $this->assertEquals('test@example.com', $found->field_value);
        $this->assertEquals('127.0.0.1', $found->ip_address);
    }

    public function test_mass_assignment_works_for_all_fillable_fields(): void
    {
        $bbValue = BbValue::create([
            'field_name' => 'username',
            'field_value' => 'john_doe',
            'ip_address' => '192.168.1.1',
        ]);

        $this->assertEquals('username', $bbValue->field_name);
        $this->assertEquals('john_doe', $bbValue->field_value);
        $this->assertEquals('192.168.1.1', $bbValue->ip_address);
    }
}
