<?php

namespace AdAstra\Blueprint;

/**
 * A single field definition within a {@see Blueprint}.
 *
 * Wraps the raw definition array so arbitrary widget-specific keys
 * (min/max/step/suffix/rows/alpha/presets/…) are preserved untouched for
 * rendering. The `handle` is the outer array key; note that a field may itself
 * declare a setting whose handle is literally `default` (e.g. Select, Country) —
 * that is distinct from the `default` *meta-attribute* read by {@see default()},
 * and the two must never be conflated.
 */
final class BlueprintField
{
    /**
     * @param string $handle              Unique key within the blueprint.
     * @param array<string, mixed> $definition Raw definition (widget keys preserved).
     */
    public function __construct(
        public readonly string $handle,
        public readonly array $definition,
    ) {
    }

    /**
     * The render widget type (text, number, select, key_value, …).
     */
    public function widget(): string
    {
        return $this->definition['type'] ?? 'text';
    }

    /**
     * The default value meta-attribute for this field (null when undeclared).
     */
    public function default(): mixed
    {
        return $this->definition['default'] ?? null;
    }

    /**
     * The validation rules for this field. Field-side semantics: the raw value
     * is passed through verbatim, falling back to the whole-value 'nullable'
     * when no rules key is declared. No auto-prepend, no boolean-skip.
     */
    public function rules(): mixed
    {
        return $this->definition['rules'] ?? 'nullable';
    }

    /**
     * Whether this field uses the two-column key/label repeater widget, whose
     * submitted rows are normalised (empty-keyed rows dropped) on filter.
     */
    public function isKeyValue(): bool
    {
        return ($this->definition['type'] ?? '') === 'key_value';
    }

    /**
     * Return a copy of this field with its `options` replaced by the given list.
     * Used during option hydration ({@see Blueprint::withOptions()}).
     *
     * @param mixed $options
     */
    public function withOptions(mixed $options): self
    {
        return new self($this->handle, array_merge($this->definition, ['options' => $options]));
    }

    /**
     * The raw definition array, for feeding the render layer.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->definition;
    }
}
