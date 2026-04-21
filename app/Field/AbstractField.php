<?php

namespace App\Field;

use App\Models\Field;

abstract class AbstractField
{
    protected array $settings = [];
    protected ?Field $field;
    protected array $rules = [];

    public function __construct(array $settings, Field $field = null)
    {
        $this->settings = $settings;
        $this->field = $field;
    }

    public function setField(Field $field): AbstractField
    {
        $this->field = $field;
        return $this;
    }

    /**
     * The field_values column this type reads and writes.
     * Must return one of: value_text, value_integer, value_float,
     *                     value_date, value_boolean, value_json
     *
     * Not called for relational field types — see isRelational().
     */
    abstract public function storageColumn(): string;

    /**
     * Relational field types store data in entry_relationships rather than
     * field_values. EntryRepository routes them to a pivot sync instead of
     * a FieldValue upsert.
     */
    public function isRelational(): bool
    {
        return false;
    }

    /**
     * Validate the given raw value for this field type.
     * Return true on success, or a string error message on failure.
     */
    public function validate(mixed $value): bool|string
    {
        return true;
    }

    /**
     * @return array
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Cast the raw stored value to the appropriate PHP type before returning it.
     */
    public function cast(mixed $value): mixed
    {
        return $value;
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function render(array $params): string
    {
        return '';
    }
}
