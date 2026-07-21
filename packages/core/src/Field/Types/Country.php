<?php

namespace AdAstra\Field\Types;

use AdAstra\Field\AbstractField;
use AdAstra\Rules\Field\CountryCodeRule;
use AdAstra\Support\Iso\Countries;

class Country extends AbstractField
{
    protected string $handle = 'country';

    protected string $name = 'Country';

    protected array $settings_form = [
        'allowed_countries' => [
            'type' => 'select_multiple',
            'label' => 'Allowed Countries',
            'options' => 'countries',
            'instructions' => 'Restrict the selectable list. Leave empty to allow every ISO 3166-1 country.',
            'default' => [],
            'rules' => 'nullable|array',
        ],
        'default' => [
            'type' => 'select',
            'label' => 'Default Country',
            'options' => 'countries',
            'instructions' => 'Pre-selected country.',
            'default' => null,
            'rules' => 'nullable|string|size:2',
        ],
        'placeholder' => [
            'type' => 'text',
            'label' => 'Placeholder',
            'default' => '— Select —',
            'rules' => 'nullable|string|max:100',
        ],
    ];

    public function settingsFormOptions(): array
    {
        $countries = array_map(
            fn ($c) => ['value' => $c['code'], 'label' => $c['name']],
            Countries::all(),
        );

        // Both `allowed_countries` and `default` are populated from the same
        // list. Field controller's buildSettingsForm() matches these keys
        // against the settings_form entries.
        return [
            'allowed_countries' => $countries,
            'default' => $countries,
        ];
    }

    public function storageColumn(): string
    {
        return 'value_text';
    }

    public function getRules(): array
    {
        return [
            'nullable',
            'string',
            new CountryCodeRule((array)$this->getSetting('allowed_countries', [])),
        ];
    }

    public function prepareForStorage(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        return strtoupper((string)$value);
    }

    public function cast(mixed $value): mixed
    {
        return $value;
    }

    /**
     * @return array{code: string, name: string}|null
     */
    public function value(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $code = strtoupper((string)$raw);
        return ['code' => $code, 'name' => Countries::name($code)];
    }

    public function render(array $params): string
    {
        $allowed = (array)$this->getSetting('allowed_countries', []);
        $all = Countries::all();

        if (!empty($allowed)) {
            $all = array_values(array_filter(
                $all,
                fn ($c) => in_array($c['code'], $allowed, true),
            ));
        }

        $params['country_options'] = $all;
        $params['placeholder'] = (string)$this->getSetting('placeholder', '— Select —');

        return view('_fields.country', $params)->render();
    }
}
