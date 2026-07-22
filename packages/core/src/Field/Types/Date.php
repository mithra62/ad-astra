<?php

namespace AdAstra\Field\Types;

use AdAstra\Field\AbstractField;

class Date extends AbstractField
{
    protected string $handle = 'date';

    protected string $name = 'Date';

    protected array $rules = [
        'date',
    ];

    protected array $settings_form = [
        'min_date' => [
            'type' => 'date',
            'label' => 'Min Date',
            'instructions' => 'Earliest allowed date (YYYY-MM-DD).',
            'default' => null,
            'rules' => 'nullable|date'
        ],
        'max_date' => [
            'type' => 'date',
            'label' => 'Max Date',
            'instructions' => 'Latest allowed date (YYYY-MM-DD).',
            'default' => null,
            'rules' => 'nullable|date'
        ],
        'default' => [
            'type' => 'text',
            'label' => 'Default Value',
            'instructions' => 'YYYY-MM-DD or "today".',
            'default' => null,
            'rules' => 'nullable|string|max:50'
        ],
        'format' => [
            'type' => 'text',
            'label' => 'Display Format',
            'instructions' => 'PHP date format string, e.g. Y-m-d.',
            'default' => null,
            'rules' => 'nullable|string|max:50'
        ],
    ];

    public function storageColumn(): string
    {
        return 'value_date';
    }

    public function render(array $params): string
    {
        return view('_fields.date', $params)->render();
    }
}
