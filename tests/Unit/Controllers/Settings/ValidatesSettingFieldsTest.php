<?php

namespace Tests\Unit\Controllers\Settings;

use App\Http\Controllers\Admin\Settings\ValidatesSettingFields;
use Tests\TestCase;

/**
 * Unit tests for the ValidatesSettingFields trait helpers.
 *
 * We test the protected methods via an anonymous class so we don't need a full
 * HTTP request — these are pure logic tests.
 */
class ValidatesSettingFieldsTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        parent::setUp();

        // Expose protected methods via anonymous class
        $this->subject = new class {
            use ValidatesSettingFields;

            public function rules(array $fields): array
            {
                return $this->settingValidationRules($fields);
            }

            public function attributes(array $fields): array
            {
                return $this->settingValidationAttributes($fields);
            }
        };
    }

    // -------------------------------------------------------------------------
    // settingValidationRules()
    // -------------------------------------------------------------------------

    public function test_fields_without_rules_are_excluded(): void
    {
        $fields = [
            ['handle' => 'foo', 'label' => 'Foo', 'type' => 'text', 'user_overridable' => false],
        ];

        $this->assertSame([], $this->subject->rules($fields));
    }

    public function test_boolean_fields_are_excluded_regardless_of_rules(): void
    {
        $fields = [
            ['handle' => 'active', 'label' => 'Active', 'type' => 'boolean', 'rules' => ['required'], 'user_overridable' => false],
        ];

        $this->assertSame([], $this->subject->rules($fields));
    }

    public function test_nullable_is_prepended_when_required_is_absent(): void
    {
        $fields = [
            ['handle' => 'email', 'label' => 'Email', 'type' => 'text', 'rules' => ['email', 'max:255'], 'user_overridable' => false],
        ];

        $rules = $this->subject->rules($fields);

        $this->assertSame(['nullable', 'email', 'max:255'], $rules['email']);
    }

    public function test_nullable_is_not_prepended_when_required_is_present(): void
    {
        $fields = [
            ['handle' => 'name', 'label' => 'Name', 'type' => 'text', 'rules' => ['required', 'string'], 'user_overridable' => false],
        ];

        $rules = $this->subject->rules($fields);

        $this->assertSame(['required', 'string'], $rules['name']);
        $this->assertNotContains('nullable', $rules['name']);
    }

    public function test_rules_are_keyed_by_handle(): void
    {
        $fields = [
            ['handle' => 'my_field', 'label' => 'My Field', 'type' => 'text', 'rules' => ['string'], 'user_overridable' => false],
        ];

        $rules = $this->subject->rules($fields);

        $this->assertArrayHasKey('my_field', $rules);
    }

    public function test_multiple_fields_produce_separate_rule_entries(): void
    {
        $fields = [
            ['handle' => 'alpha', 'label' => 'Alpha', 'type' => 'text',    'rules' => ['required', 'string'],  'user_overridable' => false],
            ['handle' => 'beta',  'label' => 'Beta',  'type' => 'integer', 'rules' => ['required', 'integer'], 'user_overridable' => false],
        ];

        $rules = $this->subject->rules($fields);

        $this->assertArrayHasKey('alpha', $rules);
        $this->assertArrayHasKey('beta', $rules);
        $this->assertCount(2, $rules);
    }

    // -------------------------------------------------------------------------
    // settingValidationAttributes()
    // -------------------------------------------------------------------------

    public function test_attributes_are_keyed_by_handle(): void
    {
        $fields = [
            ['handle' => 'timezone', 'label' => 'Timezone', 'type' => 'text', 'user_overridable' => false],
        ];

        $attributes = $this->subject->attributes($fields);

        $this->assertArrayHasKey('timezone', $attributes);
        $this->assertSame('Timezone', $attributes['timezone']);
    }

    public function test_attributes_includes_all_fields_including_those_without_rules(): void
    {
        $fields = [
            ['handle' => 'a', 'label' => 'Field A', 'type' => 'text', 'user_overridable' => false],
            ['handle' => 'b', 'label' => 'Field B', 'type' => 'text', 'rules' => ['required'], 'user_overridable' => false],
        ];

        $attributes = $this->subject->attributes($fields);

        $this->assertCount(2, $attributes);
        $this->assertSame('Field A', $attributes['a']);
        $this->assertSame('Field B', $attributes['b']);
    }
}
