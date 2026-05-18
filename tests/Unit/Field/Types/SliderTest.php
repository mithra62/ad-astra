<?php

namespace Tests\Unit\Field\Types;

use App\Field\Types\Slider;
use Tests\TestCase;

class SliderTest extends TestCase
{
    private function make(array $settings = []): Slider
    {
        return new Slider($settings, null);
    }

    // -------------------------------------------------------------------------
    // storageColumn — delegates to HasDecimalStorage trait
    // -------------------------------------------------------------------------

    public function test_storage_column_is_integer_when_decimals_is_zero(): void
    {
        $this->assertSame('value_integer', $this->make(['decimals' => 0])->storageColumn());
    }

    public function test_storage_column_is_float_when_decimals_greater_than_zero(): void
    {
        $this->assertSame('value_float', $this->make(['decimals' => 2])->storageColumn());
    }

    public function test_storage_column_defaults_to_integer_when_decimals_unset(): void
    {
        $this->assertSame('value_integer', $this->make()->storageColumn());
    }

    // -------------------------------------------------------------------------
    // settings_form
    // -------------------------------------------------------------------------

    public function test_settings_form_has_expected_keys(): void
    {
        $form = $this->make()->settingsForm();
        $this->assertArrayHasKey('min', $form);
        $this->assertArrayHasKey('max', $form);
        $this->assertArrayHasKey('step', $form);
        $this->assertArrayHasKey('suffix', $form);
        $this->assertArrayHasKey('decimals', $form);
        $this->assertArrayHasKey('default', $form);
    }

    public function test_default_setting_widget_type_is_slider(): void
    {
        $this->assertSame('slider', $this->make()->settingsForm()['default']['type']);
    }

    // -------------------------------------------------------------------------
    // validate()
    // -------------------------------------------------------------------------

    public function test_validate_returns_true_for_null(): void
    {
        $this->assertTrue($this->make(['min' => 0, 'max' => 100])->validate(null));
    }

    public function test_validate_returns_true_for_value_within_bounds(): void
    {
        $this->assertTrue($this->make(['min' => 0, 'max' => 100])->validate(50));
    }

    public function test_validate_returns_error_for_value_below_min(): void
    {
        $type   = $this->make(['min' => 10, 'max' => 100]);
        $result = $type->validate(5);
        $this->assertIsString($result);
        $this->assertStringContainsString('10', $result);
    }

    public function test_validate_returns_error_for_value_above_max(): void
    {
        $type   = $this->make(['min' => 0, 'max' => 50]);
        $result = $type->validate(75);
        $this->assertIsString($result);
        $this->assertStringContainsString('50', $result);
    }

    public function test_validate_returns_true_for_boundary_values(): void
    {
        $type = $this->make(['min' => 0, 'max' => 100]);
        $this->assertTrue($type->validate(0));
        $this->assertTrue($type->validate(100));
    }
}
