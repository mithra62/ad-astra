<?php

namespace App\Field\Types;

use App\Field\AbstractField;

class Html extends AbstractField
{
    protected string $handle = 'html';
    protected string $name = 'HTML';

    protected array $rules = [
        'nullable',
    ];

    protected array $settings_form = [
        'toolbar' => [
            'type' => 'select',
            'label' => 'Toolbar',
            'options' => 'toolbars',
            'default' => 'basic',
            'rules' => 'nullable|string|in:basic,full,minimal'
        ],
        'allowed_tags' => [
            'type' => 'text',
            'label' => 'Allowed Tags',
            'instructions' => 'Comma-separated list of allowed HTML tags, e.g. p,strong,em.',
            'default' => null,
            'rules' => 'nullable|string|max:255'
        ],
    ];

    public function settingsFormOptions(): array
    {
        return [
            'toolbars' => [
                ['value' => 'basic', 'label' => 'Basic'],
                ['value' => 'full', 'label' => 'Full'],
                ['value' => 'minimal', 'label' => 'Minimal'],
            ],
        ];
    }

    public function storageColumn(): string
    {
        return 'value_text';
    }

    public function render(array $params): string
    {
        return view('_fields.html', $params)->render();
    }
}
