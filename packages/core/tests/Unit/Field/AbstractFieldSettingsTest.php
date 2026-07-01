<?php

namespace Tests\Unit\Field;

use AdAstra\Field\AbstractField;
use AdAstra\Field\Types\EmailAddress;
use AdAstra\Field\Types\Text;
use Tests\TestCase;

class AbstractFieldSettingsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // settingsForm()
    // -------------------------------------------------------------------------

    public function test_settings_form_returns_declared_array(): void
    {
        $type = new Text([], null);

        $form = $type->settingsForm();

        $this->assertIsArray($form);
        $this->assertArrayHasKey('placeholder', $form);
        $this->assertArrayHasKey('max_length', $form);
        $this->assertArrayHasKey('min_length', $form);
    }

    public function test_settings_form_returns_empty_array_when_none_declared(): void
    {
        $type = new EmailAddress([], null);

        $this->assertSame([], $type->settingsForm());
    }

    public function test_settings_form_entry_contains_expected_keys(): void
    {
        $type = new Text([], null);
        $placeholder = $type->settingsForm()['placeholder'];

        $this->assertArrayHasKey('type', $placeholder);
        $this->assertArrayHasKey('label', $placeholder);
        $this->assertArrayHasKey('default', $placeholder);
        $this->assertArrayHasKey('rules', $placeholder);
    }

    // -------------------------------------------------------------------------
    // settingsDefaults()
    // -------------------------------------------------------------------------

    public function test_settings_defaults_returns_keyed_defaults(): void
    {
        $type = new Text([], null);

        $defaults = $type->settingsDefaults();

        $this->assertArrayHasKey('placeholder', $defaults);
        $this->assertArrayHasKey('max_length', $defaults);
        $this->assertArrayHasKey('min_length', $defaults);
    }

    public function test_settings_defaults_returns_null_for_unset_default(): void
    {
        $type = new Text([], null);

        $defaults = $type->settingsDefaults();

        $this->assertNull($defaults['placeholder']);
        $this->assertNull($defaults['max_length']);
        $this->assertNull($defaults['min_length']);
    }

    public function test_settings_defaults_returns_empty_array_when_no_settings_form(): void
    {
        $type = new EmailAddress([], null);

        $this->assertSame([], $type->settingsDefaults());
    }

    // -------------------------------------------------------------------------
    // settingsRules()
    // -------------------------------------------------------------------------

    public function test_settings_rules_keys_are_prefixed_with_settings_dot(): void
    {
        $type = new Text([], null);

        $rules = $type->settingsRules();

        $this->assertArrayHasKey('settings.placeholder', $rules);
        $this->assertArrayHasKey('settings.max_length', $rules);
        $this->assertArrayHasKey('settings.min_length', $rules);
    }

    public function test_settings_rules_values_come_from_rules_key(): void
    {
        $type = new Text([], null);

        $rules = $type->settingsRules();

        $this->assertSame('nullable|string|max:255', $rules['settings.placeholder']);
        $this->assertSame('nullable|integer|min:1', $rules['settings.max_length']);
        $this->assertSame('nullable|integer|min:0', $rules['settings.min_length']);
    }

    public function test_settings_rules_falls_back_to_nullable_when_rules_key_absent(): void
    {
        // Use an anonymous subclass with a settings_form entry that has no 'rules' key
        $type = new class([], null) extends AbstractField {
            protected array $settings_form = [
                'my_setting' => ['type' => 'text', 'label' => 'My Setting'],
            ];

            public function storageColumn(): string
            {
                return 'value_text';
            }
        };

        $rules = $type->settingsRules();

        $this->assertSame('nullable', $rules['settings.my_setting']);
    }

    public function test_settings_rules_returns_empty_array_when_no_settings_form(): void
    {
        $type = new EmailAddress([], null);

        $this->assertSame([], $type->settingsRules());
    }

    // -------------------------------------------------------------------------
    // settingsFormOptions()
    // -------------------------------------------------------------------------

    public function test_settings_form_options_returns_empty_array_by_default(): void
    {
        $type = new Text([], null);

        $this->assertSame([], $type->settingsFormOptions());
    }
}
