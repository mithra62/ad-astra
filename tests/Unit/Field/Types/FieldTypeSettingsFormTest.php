<?php

namespace Tests\Unit\Field\Types;

use App\Field\Types\Boolean;
use App\Field\Types\ColorPicker;
use App\Field\Types\Date;
use App\Field\Types\EmailAddress;
use App\Field\Types\FileUpload;
use App\Field\Types\Html;
use App\Field\Types\Number;
use App\Field\Types\Relationship;
use App\Field\Types\Telephone;
use App\Field\Types\Textarea;
use App\Field\Types\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldTypeSettingsFormTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Number — storageColumn switches on decimals setting
    // -------------------------------------------------------------------------

    public function test_number_storage_column_is_integer_when_decimals_is_zero(): void
    {
        $type = new Number(['decimals' => 0], null);

        $this->assertSame('value_integer', $type->storageColumn());
    }

    public function test_number_storage_column_is_float_when_decimals_greater_than_zero(): void
    {
        $type = new Number(['decimals' => 2], null);

        $this->assertSame('value_float', $type->storageColumn());
    }

    public function test_number_storage_column_defaults_to_integer_when_decimals_not_set(): void
    {
        $type = new Number([], null);

        $this->assertSame('value_integer', $type->storageColumn());
    }

    // -------------------------------------------------------------------------
    // $settings_form declarations — each type exposes expected keys
    // -------------------------------------------------------------------------

    public function test_textarea_settings_form_has_expected_keys(): void
    {
        $form = (new Textarea([], null))->settingsForm();

        $this->assertArrayHasKey('placeholder', $form);
        $this->assertArrayHasKey('max_length', $form);
        $this->assertArrayHasKey('rows', $form);
    }

    public function test_textarea_rows_default_is_four(): void
    {
        $defaults = (new Textarea([], null))->settingsDefaults();

        $this->assertSame(4, $defaults['rows']);
    }

    public function test_number_settings_form_has_expected_keys(): void
    {
        $form = (new Number([], null))->settingsForm();

        $this->assertArrayHasKey('min', $form);
        $this->assertArrayHasKey('max', $form);
        $this->assertArrayHasKey('step', $form);
        $this->assertArrayHasKey('decimals', $form);
        $this->assertArrayHasKey('default', $form);
    }

    public function test_number_decimals_default_is_zero(): void
    {
        $defaults = (new Number([], null))->settingsDefaults();

        $this->assertSame(0, $defaults['decimals']);
    }

    public function test_date_settings_form_has_expected_keys(): void
    {
        $form = (new Date([], null))->settingsForm();

        $this->assertArrayHasKey('min_date', $form);
        $this->assertArrayHasKey('max_date', $form);
        $this->assertArrayHasKey('default', $form);
        $this->assertArrayHasKey('format', $form);
    }

    public function test_boolean_settings_form_has_expected_keys(): void
    {
        $form = (new Boolean([], null))->settingsForm();

        $this->assertArrayHasKey('default', $form);
        $this->assertArrayHasKey('label_on', $form);
        $this->assertArrayHasKey('label_off', $form);
    }

    public function test_boolean_default_is_false(): void
    {
        $defaults = (new Boolean([], null))->settingsDefaults();

        $this->assertFalse($defaults['default']);
    }

    public function test_color_picker_settings_form_has_expected_keys(): void
    {
        $form = (new ColorPicker([], null))->settingsForm();

        $this->assertArrayHasKey('format', $form);
        $this->assertArrayHasKey('alpha', $form);
        $this->assertArrayHasKey('presets', $form);
    }

    public function test_html_settings_form_has_expected_keys(): void
    {
        $form = (new Html([], null))->settingsForm();

        $this->assertArrayHasKey('toolbar', $form);
        $this->assertArrayHasKey('allowed_tags', $form);
    }

    public function test_email_address_settings_form_is_empty(): void
    {
        $this->assertSame([], (new EmailAddress([], null))->settingsForm());
    }

    public function test_telephone_settings_form_is_empty(): void
    {
        $this->assertSame([], (new Telephone([], null))->settingsForm());
    }

    public function test_url_settings_form_is_empty(): void
    {
        $this->assertSame([], (new Url([], null))->settingsForm());
    }

    public function test_file_upload_settings_form_has_expected_keys(): void
    {
        $form = (new FileUpload([], null))->settingsForm();

        $this->assertArrayHasKey('library', $form);
        $this->assertArrayHasKey('allowed_types', $form);
        $this->assertArrayHasKey('min', $form);
        $this->assertArrayHasKey('max', $form);
    }

    public function test_relationship_settings_form_has_expected_keys(): void
    {
        $form = (new Relationship([], null))->settingsForm();

        $this->assertArrayHasKey('entry_group', $form);
        $this->assertArrayHasKey('entry_types', $form);
        $this->assertArrayHasKey('limit', $form);
    }

    // -------------------------------------------------------------------------
    // settingsFormOptions() — static options for ColorPicker and Html
    // -------------------------------------------------------------------------

    public function test_color_picker_settings_form_options_returns_formats_key(): void
    {
        $options = (new ColorPicker([], null))->settingsFormOptions();

        $this->assertArrayHasKey('formats', $options);
        $this->assertNotEmpty($options['formats']);

        $values = array_column($options['formats'], 'value');
        $this->assertContains('hex', $values);
        $this->assertContains('rgb', $values);
        $this->assertContains('hsl', $values);
    }

    public function test_html_settings_form_options_returns_toolbars_key(): void
    {
        $options = (new Html([], null))->settingsFormOptions();

        $this->assertArrayHasKey('toolbars', $options);
        $this->assertNotEmpty($options['toolbars']);

        $values = array_column($options['toolbars'], 'value');
        $this->assertContains('basic', $values);
        $this->assertContains('full', $values);
        $this->assertContains('minimal', $values);
    }

    // -------------------------------------------------------------------------
    // settingsFormOptions() — DB-backed types return arrays with expected keys
    // -------------------------------------------------------------------------

    public function test_file_upload_settings_form_options_returns_libraries_key(): void
    {
        $options = (new FileUpload([], null))->settingsFormOptions();

        $this->assertArrayHasKey('library', $options);
        $this->assertIsArray($options['library']);
    }

    public function test_relationship_settings_form_options_returns_entry_groups_and_entry_types_keys(): void
    {
        $options = (new Relationship([], null))->settingsFormOptions();

        $this->assertArrayHasKey('entry_groups', $options);
        $this->assertArrayHasKey('entry_types', $options);
        $this->assertIsArray($options['entry_groups']);
        $this->assertIsArray($options['entry_types']);
    }

    // -------------------------------------------------------------------------
    // settingsRules() — spot-check a few types
    // -------------------------------------------------------------------------

    public function test_number_settings_rules_are_prefixed_and_cover_all_keys(): void
    {
        $rules = (new Number([], null))->settingsRules();

        $this->assertArrayHasKey('settings.min', $rules);
        $this->assertArrayHasKey('settings.max', $rules);
        $this->assertArrayHasKey('settings.decimals', $rules);
    }

    public function test_relationship_settings_rules_cover_entry_group_and_limit(): void
    {
        $rules = (new Relationship([], null))->settingsRules();

        $this->assertArrayHasKey('settings.entry_group', $rules);
        $this->assertArrayHasKey('settings.limit', $rules);
    }
}
