<?php

namespace App\Field;

use App\Models\Field;

abstract class AbstractField
{
    /**
     * @var string
     */
    protected string $handle = '';

    /**
     * @var string
     */
    protected string $name = '';

    /**
     * @var array
     */
    protected array $settings = [];

    /**
     * @var array
     */
    protected array $settings_form = [];

    /**
     * @var Field|null
     */
    protected ?Field $field;

    /**
     * @var array
     */
    protected array $rules = [];

    public function __construct(array $settings, Field $field = null)
    {
        $this->settings = $settings;
        $this->field = $field;
    }

    /**
     * @param Field $field
     * @return $this
     */
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

    public function settingsForm(): array
    {
        return $this->settings_form;
    }

    public function settingsDefaults(): array
    {
        return collect($this->settings_form)
            ->map(fn($def) => $def['default'] ?? null)
            ->all();
    }

    public function settingsRules(): array
    {
        return collect($this->settings_form)
            ->mapWithKeys(fn($def, $key) => [
                "settings.{$key}" => $def['rules'] ?? 'nullable',
            ])
            ->all();
    }

    public function settingsFormOptions(): array
    {
        return [];
    }

    /**
     * @param array $params
     * @return string
     */
    public function render(array $params): string
    {
        return '';
    }

    /**
     * @return string
     */
    public function handle(): string
    {
        return $this->handle;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    public function value($value)
    {
        return $value;
    }
}
