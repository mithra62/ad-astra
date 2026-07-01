<?php

namespace Tests\Unit\Field\Types;

use AdAstra\Field\Types\Country;
use AdAstra\Rules\Field\CountryCodeRule;
use PHPUnit\Framework\TestCase;

class CountryTest extends TestCase
{
    private function make(array $settings = []): Country
    {
        return new Country($settings);
    }

    public function test_storage_column_is_value_text(): void
    {
        $this->assertSame('value_text', $this->make()->storageColumn());
    }

    public function test_get_rules_includes_country_code_rule(): void
    {
        $rules = $this->make(['allowed_countries' => ['US', 'CA']])->getRules();
        $hasRule = false;
        foreach ($rules as $rule) {
            if ($rule instanceof CountryCodeRule) {
                $hasRule = true;
            }
        }
        $this->assertTrue($hasRule);
    }

    public function test_prepare_for_storage_uppercases(): void
    {
        $this->assertSame('US', $this->make()->prepareForStorage('us'));
        $this->assertSame('US', $this->make()->prepareForStorage('US'));
    }

    public function test_prepare_for_storage_null_passthrough(): void
    {
        $this->assertNull($this->make()->prepareForStorage(null));
        $this->assertNull($this->make()->prepareForStorage(''));
    }

    public function test_value_returns_code_and_name(): void
    {
        $this->assertSame(['code' => 'US', 'name' => 'United States'], $this->make()->value('US'));
        $this->assertSame(['code' => 'US', 'name' => 'United States'], $this->make()->value('us'));
    }

    public function test_value_returns_null_for_null(): void
    {
        $this->assertNull($this->make()->value(null));
    }

    public function test_settings_form_options_use_setting_keys(): void
    {
        $options = $this->make()->settingsFormOptions();
        // Keys MUST match the settings_form entries they populate — the admin
        // settings-panel builder uses these directly.
        $this->assertArrayHasKey('allowed_countries', $options);
        $this->assertArrayHasKey('default', $options);

        $first = $options['allowed_countries'][0];
        $this->assertArrayHasKey('value', $first);
        $this->assertArrayHasKey('label', $first);
    }
}
