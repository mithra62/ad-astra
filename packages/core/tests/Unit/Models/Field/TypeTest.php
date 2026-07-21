<?php

namespace Tests\Unit\Models\Field;

use AdAstra\Field\AbstractField;
use AdAstra\Field\Types\Text;
use AdAstra\Models\Field\Type;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use stdClass;
use Tests\TestCase;

class TypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $model = new Type();

        $this->assertEquals(['name', 'object', 'settings'], $model->getFillable());
    }

    public function test_uses_field_types_table(): void
    {
        $this->assertEquals('field_types', (new Type())->getTable());
    }

    public function test_casts_settings_to_array(): void
    {
        $type = Type::factory()->create(['settings' => ['key' => 'value']]);

        $this->assertIsArray($type->settings);
        $this->assertEquals(['key' => 'value'], $type->settings);
    }

    public function test_instance_returns_abstract_field_subclass(): void
    {
        $type = Type::factory()->create(['object' => Text::class]);

        $instance = $type->instance();

        $this->assertInstanceOf(AbstractField::class, $instance);
        $this->assertInstanceOf(Text::class, $instance);
    }

    public function test_instance_throws_runtime_exception_for_nonexistent_class(): void
    {
        $type = Type::factory()->create(['object' => 'AdAstra\\Field\\Types\\NonExistent']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/does not exist/');

        $type->instance();
    }

    public function test_instance_throws_runtime_exception_for_class_not_extending_abstract_field(): void
    {
        $type = Type::factory()->create(['object' => stdClass::class]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must extend AbstractField/');

        $type->instance();
    }

    public function test_instance_passes_settings_to_field_constructor(): void
    {
        $settings = ['max_length' => 255];
        $type = Type::factory()->create(['object' => Text::class, 'settings' => $settings]);

        $instance = $type->instance();

        $this->assertEquals(255, $instance->getSetting('max_length'));
    }
}
