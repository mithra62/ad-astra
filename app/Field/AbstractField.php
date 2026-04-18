<?php

namespace App\Field;

abstract class AbstractField
{
    protected array $settings = [];

    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * The field_values column this type reads and writes.
     * Must return one of: value_text, value_integer, value_float,
     *                     value_date, value_boolean, value_json
     */
    abstract public function storageColumn(): string;

    /**
     * Validate the given raw value for this field type.
     * Return true on success, or a string error message on failure.
     */
    public function validate(mixed $value): bool|string
    {
        return true;
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
}
