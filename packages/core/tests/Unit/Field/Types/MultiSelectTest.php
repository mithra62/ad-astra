<?php

namespace Tests\Unit\Field\Types;

use AdAstra\Field\Types\MultiSelect;
use Tests\TestCase;

class MultiSelectTest extends TestCase
{
    private function sampleOptions(): array
    {
        return [
            ['key' => 'a', 'label' => 'A'],
            ['key' => 'b', 'label' => 'B'],
            ['key' => 'c', 'label' => 'C'],
        ];
    }

    private function make(array $settings = []): MultiSelect
    {
        return new MultiSelect($settings, null);
    }

    // -------------------------------------------------------------------------
    // storageColumn / basics
    // -------------------------------------------------------------------------

    public function test_storage_column_is_value_json(): void
    {
        $this->assertSame('value_json', $this->make()->storageColumn());
    }

    public function test_settings_form_has_expected_keys(): void
    {
        $form = $this->make()->settingsForm();
        $this->assertArrayHasKey('options', $form);
        $this->assertArrayHasKey('min', $form);
        $this->assertArrayHasKey('max', $form);
        $this->assertArrayHasKey('display', $form);
        $this->assertArrayHasKey('strict_options', $form);
    }

    // -------------------------------------------------------------------------
    // cast()
    // -------------------------------------------------------------------------

    public function test_cast_decodes_json_string_to_array_of_strings(): void
    {
        $result = $this->make()->cast('["a","b","c"]');
        $this->assertSame(['a', 'b', 'c'], $result);
    }

    public function test_cast_returns_array_of_strings_from_array_input(): void
    {
        $result = $this->make()->cast(['x', 'y']);
        $this->assertSame(['x', 'y'], $result);
    }

    public function test_cast_returns_empty_array_for_null(): void
    {
        $this->assertSame([], $this->make()->cast(null));
    }

    public function test_cast_returns_empty_array_for_invalid_json(): void
    {
        $this->assertSame([], $this->make()->cast('not-json'));
    }

    // -------------------------------------------------------------------------
    // validate()
    // -------------------------------------------------------------------------

    public function test_validate_returns_true_for_null(): void
    {
        $this->assertTrue($this->make(['options' => $this->sampleOptions()])->validate(null));
    }

    public function test_validate_returns_true_for_empty_array(): void
    {
        $this->assertTrue($this->make(['options' => $this->sampleOptions()])->validate([]));
    }

    public function test_validate_enforces_minimum(): void
    {
        $type   = $this->make(['options' => $this->sampleOptions(), 'min' => 2]);
        $result = $type->validate(['a']);
        $this->assertIsString($result);
        $this->assertStringContainsString('2', $result);
    }

    public function test_validate_enforces_maximum(): void
    {
        $type   = $this->make(['options' => $this->sampleOptions(), 'max' => 1]);
        $result = $type->validate(['a', 'b']);
        $this->assertIsString($result);
        $this->assertStringContainsString('1', $result);
    }

    public function test_validate_returns_true_when_within_bounds(): void
    {
        $type = $this->make(['options' => $this->sampleOptions(), 'min' => 1, 'max' => 3]);
        $this->assertTrue($type->validate(['a', 'b']));
    }

    public function test_validate_orphaned_values_pass_when_not_strict(): void
    {
        $type = $this->make(['options' => $this->sampleOptions(), 'strict_options' => false]);
        $this->assertTrue($type->validate(['a', 'orphaned']));
    }

    public function test_validate_orphaned_values_fail_when_strict(): void
    {
        $type   = $this->make(['options' => $this->sampleOptions(), 'strict_options' => true]);
        $result = $type->validate(['a', 'orphaned']);
        $this->assertIsString($result);
    }
}
