<?php

namespace App\Field\Types;

use App\Field\AbstractField;

class ColorPicker extends AbstractField
{
    protected string $handle = 'color_picker';

    protected string $name = 'Color Picker';

    protected array $settings_form = [
        'format' => [
            'type' => 'select',
            'label' => 'Color Format',
            'options' => 'formats',
            'instructions' => 'Format to store the color value.',
            'default' => 'hex',
            'rules' => 'nullable|string|in:hex,rgb,hsl'
        ],
        'alpha' => [
            'type' => 'toggle',
            'label' => 'Allow Alpha',
            'instructions' => 'Enables transparency/opacity control.',
            'default' => false,
            'rules' => 'nullable|boolean'
        ],
        'presets' => [
            'type' => 'key_value',
            'label' => 'Preset Colors',
            'instructions' => 'Optional list of preset color swatches (key = label, value = hex).',
            'default' => [],
            'rules' => 'nullable|array'
        ],
    ];

    public function settingsFormOptions(): array
    {
        return [
            'formats' => [
                ['value' => 'hex', 'label' => 'Hex (#rrggbb)'],
                ['value' => 'rgb', 'label' => 'RGB'],
                ['value' => 'hsl', 'label' => 'HSL'],
            ],
        ];
    }

    public function storageColumn(): string
    {
        return 'value_text';
    }

    public function render(array $params): string
    {
        return view('_fields.color_picker', $params)->render();
    }
}
