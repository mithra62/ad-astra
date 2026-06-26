<?php

namespace App\Field\Types;

use App\Field\AbstractField;
use App\Rules\Field\TimeFormatRule;
use App\Support\Iso\TimeValue;
use InvalidArgumentException;

class Time extends AbstractField
{
    protected string $handle = 'time';

    protected string $name = 'Time';

    protected array $settings_form = [
        'include_seconds' => [
            'type' => 'toggle',
            'label' => 'Include Seconds',
            'instructions' => 'When on, stored values include a seconds component (HH:MM:SS).',
            'default' => false,
            'rules' => 'nullable|boolean',
        ],
        'min_time' => [
            'type' => 'text',
            'label' => 'Minimum Time',
            'instructions' => 'Earliest allowed time. HH:MM or HH:MM:SS.',
            'default' => null,
            'rules' => 'nullable|string|max:8',
        ],
        'max_time' => [
            'type' => 'text',
            'label' => 'Maximum Time',
            'instructions' => 'Latest allowed time. HH:MM or HH:MM:SS.',
            'default' => null,
            'rules' => 'nullable|string|max:8',
        ],
        'step_minutes' => [
            'type' => 'number',
            'label' => 'Step (minutes)',
            'instructions' => 'UI step granularity. Drives the HTML input step attribute.',
            'default' => 1,
            'rules' => 'nullable|integer|min:1|max:60',
        ],
        'default' => [
            'type' => 'text',
            'label' => 'Default Value',
            'instructions' => 'Pre-filled time, or the literal "now".',
            'default' => null,
            'rules' => 'nullable|string|max:8',
        ],
    ];

    public function storageColumn(): string
    {
        return 'value_text';
    }

    public function getRules(): array
    {
        return [
            'nullable',
            'string',
            new TimeFormatRule(
                includeSeconds: (bool)$this->getSetting('include_seconds', false),
                minTime: $this->getSetting('min_time'),
                maxTime: $this->getSetting('max_time'),
            ),
        ];
    }

    /**
     * Canonicalize HH:MM(:SS) — zero-pad hour and align the seconds component
     * with the include_seconds setting. Throws on malformed input.
     */
    public function prepareForStorage(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('Time value must be a string.');
        }

        $value = trim($value);
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $value, $m)) {
            throw new InvalidArgumentException("Time value is not a valid HH:MM or HH:MM:SS string: {$value}.");
        }

        $hh = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $mm = $m[2];
        $ss = $m[3] ?? null;
        $includeSeconds = (bool)$this->getSetting('include_seconds', false);

        if ($includeSeconds) {
            return "{$hh}:{$mm}:" . ($ss ?? '00');
        }

        return "{$hh}:{$mm}";
    }

    public function cast(mixed $value): mixed
    {
        return $value;
    }

    public function value(mixed $raw): ?TimeValue
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        return TimeValue::fromCanonical((string)$raw);
    }

    public function render(array $params): string
    {
        $stepMinutes = (int)$this->getSetting('step_minutes', 1);
        $params['step_seconds'] = $stepMinutes * 60;
        $params['include_seconds'] = (bool)$this->getSetting('include_seconds', false);
        $params['min_time'] = $this->getSetting('min_time');
        $params['max_time'] = $this->getSetting('max_time');

        $default = $this->getSetting('default');
        if ($default === 'now') {
            $default = date($params['include_seconds'] ? 'H:i:s' : 'H:i');
        }
        $params['default'] = $default;

        return view('_fields.time', $params)->render();
    }
}
