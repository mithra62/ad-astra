<?php

namespace AdAstra\Traits\Field;

trait ValidatesAgainstOptions
{
    /**
     * Returns true when $value is one of the declared option keys.
     *
     * @param array<int, array{key: string, label: string}> $options
     */
    public function isValidOption(mixed $value, array $options): bool
    {
        $keys = array_column($options, 'key');
        return in_array((string) $value, array_map('strval', $keys), true);
    }

    /**
     * Validates $value against the configured options.
     *
     * When strict_options is false (default), orphaned values pass silently.
     * When strict_options is true, an orphaned value returns an error string.
     */
    public function validateAgainstOptions(mixed $value): bool|string
    {
        if ($value === null || $value === '' || $value === []) {
            return true;
        }

        $options = $this->getSetting('options', []);

        if (empty($options)) {
            return true;
        }

        $strict = (bool) $this->getSetting('strict_options', false);

        $values = is_array($value) ? $value : [$value];

        foreach ($values as $v) {
            if (!$this->isValidOption($v, $options)) {
                if ($strict) {
                    return "The selected value \"{$v}\" is no longer a valid option.";
                }
            }
        }

        return true;
    }

    /**
     * Returns HTML for an orphaned value indicator, for use in render() methods.
     */
    public function renderOrphanedValue(mixed $value, array $options): string
    {
        if ($this->isValidOption($value, $options)) {
            return '';
        }

        $escaped = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        return "<option value=\"{$escaped}\" disabled selected class=\"text-red-500\" data-orphaned=\"true\">[orphaned: {$escaped}]</option>";
    }
}
