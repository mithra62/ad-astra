<?php

namespace Tests\Unit\Field\Types;

use AdAstra\Field\Types\RadioGroup;
use Tests\TestCase;

class RadioGroupTest extends TestCase
{
    public function test_storage_column_is_value_text(): void
    {
        $this->assertSame('value_text', $this->make()->storageColumn());
    }

    private function make(array $settings = []): RadioGroup
    {
        return new RadioGroup($settings, null);
    }

    public function test_settings_form_has_expected_keys(): void
    {
        $form = $this->make()->settingsForm();
        $this->assertArrayHasKey('options', $form);
        $this->assertArrayHasKey('default', $form);
        $this->assertArrayHasKey('layout', $form);
        $this->assertArrayHasKey('strict_options', $form);
    }

    public function test_validate_returns_true_for_null(): void
    {
        $this->assertTrue($this->make(['options' => $this->sampleOptions()])->validate(null));
    }

    private function sampleOptions(): array
    {
        return [
            ['key' => 'yes', 'label' => 'Yes'],
            ['key' => 'no', 'label' => 'No'],
            ['key' => 'maybe', 'label' => 'Maybe'],
        ];
    }

    public function test_validate_returns_true_for_valid_option(): void
    {
        $this->assertTrue($this->make(['options' => $this->sampleOptions()])->validate('yes'));
    }

    public function test_orphaned_value_passes_when_not_strict(): void
    {
        $type = $this->make(['options' => $this->sampleOptions(), 'strict_options' => false]);
        $this->assertTrue($type->validate('gone'));
    }

    public function test_orphaned_value_fails_when_strict(): void
    {
        $type = $this->make(['options' => $this->sampleOptions(), 'strict_options' => true]);
        $result = $type->validate('gone');
        $this->assertIsString($result);
        $this->assertStringContainsString('gone', $result);
    }

    public function test_orphaned_indicator_html_contains_data_attribute(): void
    {
        $type = $this->make();
        $html = $type->renderOrphanedValue('old', $this->sampleOptions());
        $this->assertStringContainsString('data-orphaned="true"', $html);
    }
}
