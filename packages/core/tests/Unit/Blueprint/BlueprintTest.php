<?php

namespace Tests\Unit\Blueprint;

use AdAstra\Blueprint\Blueprint;
use AdAstra\Blueprint\BlueprintField;
use Tests\TestCase;

class BlueprintTest extends TestCase
{
    /**
     * A representative definition mirroring a field-type $settings_form.
     *
     * @return array<string, mixed>
     */
    private function definition(): array
    {
        return [
            'placeholder' => [
                'type' => 'text',
                'label' => 'Placeholder',
                'default' => null,
                'rules' => 'nullable|string|max:255',
            ],
            'max_length' => [
                'type' => 'number',
                'label' => 'Max Length',
                'default' => 10,
                'rules' => 'nullable|integer|min:1',
            ],
            'options' => [
                'type' => 'key_value',
                'label' => 'Options',
                'default' => [],
                'rules' => 'required|array|min:1',
            ],
            // No 'rules' key — exercises the whole-value 'nullable' fallback.
            'note' => [
                'type' => 'text',
                'label' => 'Note',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // fromArray() / fields()
    // -------------------------------------------------------------------------

    public function test_from_array_builds_a_field_per_handle(): void
    {
        $blueprint = Blueprint::fromArray($this->definition());

        $fields = $blueprint->fields();

        $this->assertCount(4, $fields);
        $this->assertContainsOnlyInstancesOf(BlueprintField::class, $fields);
        $this->assertSame(['placeholder', 'max_length', 'options', 'note'], array_keys($fields));
    }

    // -------------------------------------------------------------------------
    // defaults()
    // -------------------------------------------------------------------------

    public function test_defaults_returns_declared_defaults(): void
    {
        $defaults = Blueprint::fromArray($this->definition())->defaults();

        $this->assertNull($defaults['placeholder']);
        $this->assertSame(10, $defaults['max_length']);
        $this->assertSame([], $defaults['options']);
    }

    public function test_defaults_are_null_when_undeclared(): void
    {
        $defaults = Blueprint::fromArray($this->definition())->defaults();

        $this->assertArrayHasKey('note', $defaults);
        $this->assertNull($defaults['note']);
    }

    // -------------------------------------------------------------------------
    // rules()
    // -------------------------------------------------------------------------

    public function test_rules_prefixes_handles(): void
    {
        $rules = Blueprint::fromArray($this->definition())->rules('settings');

        $this->assertArrayHasKey('settings.placeholder', $rules);
        $this->assertArrayHasKey('settings.max_length', $rules);
        $this->assertSame('nullable|string|max:255', $rules['settings.placeholder']);
    }

    public function test_rules_falls_back_to_nullable_when_absent(): void
    {
        $rules = Blueprint::fromArray($this->definition())->rules('settings');

        $this->assertSame('nullable', $rules['settings.note']);
    }

    public function test_rules_without_prefix_uses_bare_handles(): void
    {
        $rules = Blueprint::fromArray($this->definition())->rules();

        $this->assertArrayHasKey('placeholder', $rules);
        $this->assertArrayNotHasKey('settings.placeholder', $rules);
    }

    // -------------------------------------------------------------------------
    // filter()
    // -------------------------------------------------------------------------

    public function test_filter_strips_undeclared_keys(): void
    {
        $filtered = Blueprint::fromArray($this->definition())->filter([
            'placeholder' => 'Hi',
            'injected' => 'should vanish',
        ]);

        $this->assertArrayHasKey('placeholder', $filtered);
        $this->assertArrayNotHasKey('injected', $filtered);
        $this->assertSame('Hi', $filtered['placeholder']);
    }

    public function test_filter_fills_defaults_for_missing_keys(): void
    {
        $filtered = Blueprint::fromArray($this->definition())->filter([
            'placeholder' => 'Hi',
        ]);

        $this->assertSame(10, $filtered['max_length']);
        $this->assertNull($filtered['note']);
    }

    public function test_filter_normalises_key_value_dropping_empty_key_rows(): void
    {
        $filtered = Blueprint::fromArray($this->definition())->filter([
            'options' => [
                ['key' => 'red', 'label' => 'Red'],
                ['key' => '', 'label' => ''],
                ['key' => 'blue', 'label' => 'Blue'],
                ['key' => '   ', 'label' => 'Whitespace only'],
            ],
        ]);

        $this->assertCount(2, $filtered['options']);
        $this->assertSame('red', $filtered['options'][0]['key']);
        $this->assertSame('blue', $filtered['options'][1]['key']);
    }

    // -------------------------------------------------------------------------
    // withOptions() / form()
    // -------------------------------------------------------------------------

    public function test_with_options_injects_and_is_immutable(): void
    {
        $blueprint = Blueprint::fromArray([
            'roles' => ['type' => 'select_multiple', 'label' => 'Roles', 'options' => 'roles'],
            'limit' => ['type' => 'number', 'label' => 'Limit'],
        ]);

        $hydrated = $blueprint->withOptions([
            'roles' => [['value' => 1, 'label' => 'Admin']],
            'unknown' => [['value' => 9, 'label' => 'Ignored']],
        ]);

        // Original is untouched (placeholder string still present).
        $this->assertSame('roles', $blueprint->form()['roles']['options']);

        // Hydrated copy has the real list on the named handle...
        $hydratedForm = $hydrated->form();
        $this->assertSame([['value' => 1, 'label' => 'Admin']], $hydratedForm['roles']['options']);

        // ...leaves un-mapped handles alone and ignores unknown handles.
        $this->assertArrayNotHasKey('options', $hydratedForm['limit']);
        $this->assertArrayNotHasKey('unknown', $hydratedForm);
    }

    public function test_form_returns_raw_definitions(): void
    {
        $definition = $this->definition();

        $this->assertSame($definition, Blueprint::fromArray($definition)->form());
    }

    // -------------------------------------------------------------------------
    // 'default' handle vs 'default' meta-attribute (footgun guard)
    // -------------------------------------------------------------------------

    public function test_a_field_handled_default_is_not_confused_with_the_default_meta_key(): void
    {
        $blueprint = Blueprint::fromArray([
            'default' => [
                'type' => 'text',
                'label' => 'Default Value',
                'default' => 'pre-selected',
                'rules' => 'nullable|string',
            ],
        ]);

        // The handle 'default' survives as its own field...
        $this->assertArrayHasKey('default', $blueprint->fields());
        // ...its default meta-value resolves correctly...
        $this->assertSame('pre-selected', $blueprint->defaults()['default']);
        // ...and it validates + filters like any other handle.
        $this->assertSame('nullable|string', $blueprint->rules('settings')['settings.default']);
        $this->assertSame('chosen', $blueprint->filter(['default' => 'chosen'])['default']);
    }
}
