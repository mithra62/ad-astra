<?php

namespace Tests\Unit\Field\Types;

use AdAstra\Field\Types\Select;
use Tests\TestCase;

class SelectTest extends TestCase
{
    private function sampleOptions(): array
    {
        return [
            ['key' => 'red', 'label' => 'Red'],
            ['key' => 'green', 'label' => 'Green'],
            ['key' => 'blue', 'label' => 'Blue'],
        ];
    }

    private function make(array $settings = []): Select
    {
        return new Select($settings, null);
    }

    // -------------------------------------------------------------------------
    // storageColumn / basics
    // -------------------------------------------------------------------------

    public function test_storage_column_is_value_text(): void
    {
        $this->assertSame('value_text', $this->make()->storageColumn());
    }

    public function test_settings_form_has_expected_keys(): void
    {
        $form = $this->make()->settingsForm();
        $this->assertArrayHasKey('options', $form);
        $this->assertArrayHasKey('placeholder', $form);
        $this->assertArrayHasKey('default', $form);
        $this->assertArrayHasKey('strict_options', $form);
    }

    // -------------------------------------------------------------------------
    // validate()
    // -------------------------------------------------------------------------

    public function test_validate_returns_true_for_null_value(): void
    {
        $this->assertTrue($this->make(['options' => $this->sampleOptions()])->validate(null));
    }

    public function test_validate_returns_true_for_empty_string(): void
    {
        $this->assertTrue($this->make(['options' => $this->sampleOptions()])->validate(''));
    }

    public function test_validate_returns_true_for_valid_option_key(): void
    {
        $this->assertTrue($this->make(['options' => $this->sampleOptions()])->validate('red'));
    }

    public function test_validate_returns_true_for_orphaned_value_when_not_strict(): void
    {
        $type = $this->make(['options' => $this->sampleOptions(), 'strict_options' => false]);
        $this->assertTrue($type->validate('purple'));
    }

    public function test_validate_returns_error_for_orphaned_value_when_strict(): void
    {
        $type   = $this->make(['options' => $this->sampleOptions(), 'strict_options' => true]);
        $result = $type->validate('purple');
        $this->assertIsString($result);
        $this->assertStringContainsString('purple', $result);
    }

    // -------------------------------------------------------------------------
    // renderOrphanedValue() via trait
    // -------------------------------------------------------------------------

    public function test_orphaned_value_indicator_contains_data_attribute(): void
    {
        $type = $this->make();
        $html = $type->renderOrphanedValue('gone', $this->sampleOptions());
        $this->assertStringContainsString('data-orphaned="true"', $html);
    }

    public function test_valid_value_returns_empty_orphaned_indicator(): void
    {
        $type = $this->make();
        $this->assertSame('', $type->renderOrphanedValue('red', $this->sampleOptions()));
    }
}
