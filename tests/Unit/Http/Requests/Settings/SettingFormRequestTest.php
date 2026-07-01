<?php

namespace Tests\Unit\Http\Requests\Settings;

use AdAstra\Http\Requests\Settings\SettingFormRequest;
use Tests\TestCase;

/**
 * Unit tests for the shared helpers on SettingFormRequest.
 *
 * settingRulesFromFields() and settingAttributesFromFields() are pure array
 * transforms with no dependency on HTTP request state, so they are exercised
 * through a lightweight anonymous subclass without needing a full request cycle.
 *
 * normaliseFields() reads from the underlying request (has() / only()), so it
 * is tested via the static ::create() factory which provides a real request
 * object populated with submitted data.
 */
class SettingFormRequestTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Test doubles
    // -------------------------------------------------------------------------

    public function test_fields_with_no_rules_key_are_excluded(): void
    {
        $subject = $this->makeSubject();
        $fields = [
            ['handle' => 'foo', 'label' => 'Foo', 'type' => 'text', 'user_overridable' => false],
        ];

        $this->assertSame([], $subject->exposedRules($fields));
    }

    /**
     * A concrete SettingFormRequest subclass that exposes every protected helper
     * as a public method so tests can call them directly.
     *
     * @return object  Anonymous subclass of SettingFormRequest.
     */
    private function makeSubject(): object
    {
        return new class extends SettingFormRequest {
            public function authorize(): bool
            {
                return true;
            }

            public function rules(): array
            {
                return [];
            }

            public function settingsPayload(): array
            {
                return [];
            }

            public function exposedRules(array $fields): array
            {
                return $this->settingRulesFromFields($fields);
            }

            public function exposedAttributes(array $fields): array
            {
                return $this->settingAttributesFromFields($fields);
            }
        };
    }

    // -------------------------------------------------------------------------
    // settingRulesFromFields()
    // -------------------------------------------------------------------------

    public function test_boolean_fields_are_excluded_regardless_of_rules(): void
    {
        $subject = $this->makeSubject();
        $fields = [
            ['handle' => 'active', 'label' => 'Active', 'type' => 'boolean', 'rules' => ['required'], 'user_overridable' => false],
        ];

        $this->assertSame([], $subject->exposedRules($fields));
    }

    public function test_nullable_is_prepended_when_required_is_absent(): void
    {
        $subject = $this->makeSubject();
        $fields = [
            ['handle' => 'email', 'label' => 'Email', 'type' => 'text', 'rules' => ['email', 'max:255'], 'user_overridable' => false],
        ];

        $rules = $subject->exposedRules($fields);

        $this->assertSame(['nullable', 'email', 'max:255'], $rules['email']);
    }

    public function test_nullable_is_not_prepended_when_required_is_present(): void
    {
        $subject = $this->makeSubject();
        $fields = [
            ['handle' => 'name', 'label' => 'Name', 'type' => 'text', 'rules' => ['required', 'string'], 'user_overridable' => false],
        ];

        $rules = $subject->exposedRules($fields);

        $this->assertSame(['required', 'string'], $rules['name']);
        $this->assertNotContains('nullable', $rules['name']);
    }

    public function test_rules_are_keyed_by_field_handle(): void
    {
        $subject = $this->makeSubject();
        $fields = [
            ['handle' => 'my_setting', 'label' => 'My Setting', 'type' => 'text', 'rules' => ['string'], 'user_overridable' => false],
        ];

        $rules = $subject->exposedRules($fields);

        $this->assertArrayHasKey('my_setting', $rules);
        $this->assertArrayNotHasKey('fields.my_setting', $rules);
    }

    public function test_multiple_fields_produce_separate_rule_entries(): void
    {
        $subject = $this->makeSubject();
        $fields = [
            ['handle' => 'alpha', 'label' => 'Alpha', 'type' => 'text', 'rules' => ['required', 'string'], 'user_overridable' => false],
            ['handle' => 'beta', 'label' => 'Beta', 'type' => 'integer', 'rules' => ['required', 'integer'], 'user_overridable' => false],
        ];

        $rules = $subject->exposedRules($fields);

        $this->assertArrayHasKey('alpha', $rules);
        $this->assertArrayHasKey('beta', $rules);
        $this->assertCount(2, $rules);
    }

    public function test_empty_fields_array_returns_empty_rules(): void
    {
        $subject = $this->makeSubject();

        $this->assertSame([], $subject->exposedRules([]));
    }

    public function test_mixed_boolean_and_text_fields_only_include_text_in_rules(): void
    {
        $subject = $this->makeSubject();
        $fields = [
            ['handle' => 'label', 'label' => 'Label', 'type' => 'text', 'rules' => ['required', 'string'], 'user_overridable' => false],
            ['handle' => 'enabled', 'label' => 'Enabled', 'type' => 'boolean', 'rules' => ['required'], 'user_overridable' => false],
        ];

        $rules = $subject->exposedRules($fields);

        $this->assertArrayHasKey('label', $rules);
        $this->assertArrayNotHasKey('enabled', $rules);
        $this->assertCount(1, $rules);
    }

    public function test_attributes_are_keyed_by_field_handle_with_label_as_value(): void
    {
        $subject = $this->makeSubject();
        $fields = [
            ['handle' => 'timezone', 'label' => 'Timezone', 'type' => 'text', 'user_overridable' => false],
        ];

        $attributes = $subject->exposedAttributes($fields);

        $this->assertArrayHasKey('timezone', $attributes);
        $this->assertSame('Timezone', $attributes['timezone']);
        $this->assertArrayNotHasKey('fields.timezone', $attributes);
    }

    // -------------------------------------------------------------------------
    // settingAttributesFromFields()
    // -------------------------------------------------------------------------

    public function test_attributes_includes_all_fields_regardless_of_rules(): void
    {
        $subject = $this->makeSubject();
        $fields = [
            ['handle' => 'a', 'label' => 'Field A', 'type' => 'text', 'user_overridable' => false],
            ['handle' => 'b', 'label' => 'Field B', 'type' => 'text', 'rules' => ['required'], 'user_overridable' => false],
            ['handle' => 'c', 'label' => 'Field C', 'type' => 'boolean', 'user_overridable' => false],
        ];

        $attributes = $subject->exposedAttributes($fields);

        $this->assertCount(3, $attributes);
        $this->assertSame('Field A', $attributes['a']);
        $this->assertSame('Field B', $attributes['b']);
        $this->assertSame('Field C', $attributes['c']);
    }

    public function test_empty_fields_array_returns_empty_attributes(): void
    {
        $subject = $this->makeSubject();

        $this->assertSame([], $subject->exposedAttributes([]));
    }

    public function test_normalise_returns_submitted_text_value(): void
    {
        $subject = $this->makeNormaliseSubject(['site_name' => 'Acme Corp']);
        $fields = [
            ['handle' => 'site_name', 'type' => 'text', 'label' => 'Site Name', 'user_overridable' => false],
        ];

        $result = $subject->exposedNormalise($fields);

        $this->assertSame('Acme Corp', $result['site_name']);
    }

    // -------------------------------------------------------------------------
    // normaliseFields()
    // -------------------------------------------------------------------------

    /**
     * A concrete SettingFormRequest subclass suitable for testing normaliseFields().
     *
     * Data is placed in the request (POST) bag and REQUEST_METHOD is set to POST
     * so that Laravel's getInputSource() resolves to the request bag rather than
     * the query bag (the default for GET requests), making has() / only() work.
     */
    private function makeNormaliseSubject(array $postData): object
    {
        return (new class($postData) extends SettingFormRequest {
            public function __construct(private array $postData)
            {
                parent::__construct(
                    query: [],
                    request: $this->postData,
                    server: ['REQUEST_METHOD' => 'POST'],
                );
            }

            public function authorize(): bool
            {
                return true;
            }

            public function rules(): array
            {
                return [];
            }

            public function settingsPayload(): array
            {
                return [];
            }

            public function exposedNormalise(array $fields): array
            {
                return $this->normaliseFields($fields);
            }
        });
    }

    public function test_normalise_sets_present_boolean_to_true(): void
    {
        $subject = $this->makeNormaliseSubject(['enabled' => '1']);
        $fields = [
            ['handle' => 'enabled', 'type' => 'boolean', 'label' => 'Enabled', 'user_overridable' => false],
        ];

        $result = $subject->exposedNormalise($fields);

        $this->assertTrue($result['enabled']);
    }

    public function test_normalise_sets_absent_boolean_to_false(): void
    {
        // 'enabled' is not in the POST body — simulates an unchecked checkbox
        $subject = $this->makeNormaliseSubject(['other_field' => 'value']);
        $fields = [
            ['handle' => 'enabled', 'type' => 'boolean', 'label' => 'Enabled', 'user_overridable' => false],
        ];

        $result = $subject->exposedNormalise($fields);

        $this->assertFalse($result['enabled']);
    }

    public function test_normalise_excludes_handles_not_in_fields(): void
    {
        $subject = $this->makeNormaliseSubject([
            'known' => 'yes',
            'unknown' => 'should not appear',
        ]);
        $fields = [
            ['handle' => 'known', 'type' => 'text', 'label' => 'Known', 'user_overridable' => false],
        ];

        $result = $subject->exposedNormalise($fields);

        $this->assertArrayHasKey('known', $result);
        $this->assertArrayNotHasKey('unknown', $result);
    }

    public function test_normalise_handles_mixed_text_and_boolean_fields(): void
    {
        $subject = $this->makeNormaliseSubject([
            'site_name' => 'Hello',
            'enabled' => '1',
            // 'archived' is absent — unchecked checkbox
        ]);
        $fields = [
            ['handle' => 'site_name', 'type' => 'text', 'label' => 'Site Name', 'user_overridable' => false],
            ['handle' => 'enabled', 'type' => 'boolean', 'label' => 'Enabled', 'user_overridable' => false],
            ['handle' => 'archived', 'type' => 'boolean', 'label' => 'Archived', 'user_overridable' => false],
        ];

        $result = $subject->exposedNormalise($fields);

        $this->assertSame('Hello', $result['site_name']);
        $this->assertTrue($result['enabled']);
        $this->assertFalse($result['archived']);
    }

    public function test_normalise_returns_empty_array_for_empty_fields(): void
    {
        $subject = $this->makeNormaliseSubject(['anything' => 'value']);

        $this->assertSame([], $subject->exposedNormalise([]));
    }
}
