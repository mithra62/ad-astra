<?php

namespace AdAstra\Blueprint;

/**
 * An ordered set of typed field definitions plus the operations over it —
 * the single point of entry for the dynamic settings/fieldset machinery.
 *
 * Built from a `$settings_form`-shaped array (handle => definition). Immutable:
 * {@see withOptions()} returns a hydrated copy rather than mutating in place.
 *
 * The four operations consolidate what used to be scattered across the Field
 * layer (AbstractField::settingsDefaults/settingsRules, FiltersFieldSettings,
 * and the controller's buildSettingsForm option merge):
 *
 *   defaults()    – handle => default meta-value
 *   rules()       – ("{prefix}.{handle}" | "{handle}") => rules
 *   filter()      – sanitise submitted input against the schema
 *   withOptions() – inject dynamic option lists for rendering
 */
final class Blueprint
{
    /**
     * @param array<string, BlueprintField> $fields Keyed by handle, order preserved.
     */
    private function __construct(
        private readonly array $fields,
    ) {
    }

    /**
     * Build a blueprint from a raw definition array (handle => definition).
     *
     * @param array<string, mixed> $definition
     */
    public static function fromArray(array $definition): self
    {
        $fields = [];

        foreach ($definition as $handle => $def) {
            $fields[$handle] = new BlueprintField((string) $handle, is_array($def) ? $def : []);
        }

        return new self($fields);
    }

    /**
     * @return array<string, BlueprintField>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * Map each handle to its declared default (null when undeclared).
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $out = [];

        foreach ($this->fields as $handle => $field) {
            $out[$handle] = $field->default();
        }

        return $out;
    }

    /**
     * Build a Laravel rules array keyed by handle, optionally namespaced under
     * a prefix (e.g. 'settings' → 'settings.{handle}'). Field-side semantics:
     * raw rule passthrough with a whole-value 'nullable' fallback.
     *
     * @return array<string, mixed>
     */
    public function rules(string $prefix = ''): array
    {
        $out = [];

        foreach ($this->fields as $handle => $field) {
            $key = $prefix === '' ? $handle : "{$prefix}.{$handle}";
            $out[$key] = $field->rules();
        }

        return $out;
    }

    /**
     * Sanitise a submitted values array against the schema: keep only declared
     * handles, fill missing ones with their defaults, and normalise key_value
     * rows (dropping rows with an empty key).
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public function filter(array $raw): array
    {
        $defaults = $this->defaults();
        $out = [];

        foreach ($this->fields as $handle => $field) {
            if (array_key_exists($handle, $raw)) {
                $value = $raw[$handle];

                if ($field->isKeyValue()) {
                    $value = $this->normaliseKeyValue((array) $value);
                }

                $out[$handle] = $value;
            } else {
                $out[$handle] = $defaults[$handle] ?? null;
            }
        }

        return $out;
    }

    /**
     * Return a hydrated copy with dynamic option lists injected onto the named
     * fields. Handles not present in $byHandle are left untouched; unknown
     * handles are ignored.
     *
     * @param array<string, mixed> $byHandle handle => options list
     */
    public function withOptions(array $byHandle): self
    {
        $fields = $this->fields;

        foreach ($byHandle as $handle => $options) {
            if (isset($fields[$handle])) {
                $fields[$handle] = $fields[$handle]->withOptions($options);
            }
        }

        return new self($fields);
    }

    /**
     * The raw definition array (handle => definition), for the render layer.
     *
     * @return array<string, mixed>
     */
    public function form(): array
    {
        $out = [];

        foreach ($this->fields as $handle => $field) {
            $out[$handle] = $field->toArray();
        }

        return $out;
    }

    /**
     * Drop repeater rows whose key is blank, then re-index.
     *
     * @param array<int, mixed> $raw
     * @return array<int, mixed>
     */
    private function normaliseKeyValue(array $raw): array
    {
        return array_values(
            array_filter(
                $raw,
                fn ($row) => trim($row['key'] ?? '') !== ''
            )
        );
    }
}
