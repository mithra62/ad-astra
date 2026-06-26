<?php

namespace Tests\Unit\Field\Types;

use App\Field\Types\StateProvince;
use App\Rules\Field\SubdivisionCodeRule;
use PHPUnit\Framework\TestCase;

class StateProvinceTest extends TestCase
{
    private function make(array $settings = []): StateProvince
    {
        return new StateProvince($settings);
    }

    public function test_storage_column_is_value_text(): void
    {
        $this->assertSame('value_text', $this->make()->storageColumn());
    }

    public function test_get_rules_includes_subdivision_rule(): void
    {
        $rules = $this->make(['country' => 'US'])->getRules();
        $hasRule = false;
        foreach ($rules as $rule) {
            if ($rule instanceof SubdivisionCodeRule) {
                $hasRule = true;
            }
        }
        $this->assertTrue($hasRule);
    }

    public function test_prepare_for_storage_returns_string(): void
    {
        $this->assertSame('US-CA', $this->make(['country' => 'US'])->prepareForStorage('US-CA'));
    }

    public function test_prepare_for_storage_null_passthrough(): void
    {
        $this->assertNull($this->make()->prepareForStorage(null));
        $this->assertNull($this->make()->prepareForStorage(''));
    }

    public function test_value_for_data_backed_country(): void
    {
        $result = $this->make(['country' => 'US'])->value('US-CA');
        $this->assertSame(['code' => 'US-CA', 'name' => 'California', 'country' => 'US'], $result);
    }

    public function test_value_for_freetext_country(): void
    {
        // Finland is not in the v1 subdivision subset.
        $result = $this->make(['country' => 'FI'])->value('Uusimaa');
        $this->assertSame(['code' => 'Uusimaa', 'name' => 'Uusimaa', 'country' => 'FI'], $result);
    }

    public function test_value_returns_null_for_null(): void
    {
        $this->assertNull($this->make()->value(null));
    }

    public function test_settings_form_options_use_setting_key(): void
    {
        $options = $this->make()->settingsFormOptions();
        // Key MUST match the settings_form entry it populates ("country") —
        // the admin settings-panel builder uses this directly.
        $this->assertArrayHasKey('country', $options);
        $first = $options['country'][0];
        $this->assertArrayHasKey('value', $first);
        $this->assertArrayHasKey('label', $first);
    }
}
