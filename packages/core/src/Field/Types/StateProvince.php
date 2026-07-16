<?php

namespace AdAstra\Field\Types;

use AdAstra\Field\AbstractField;
use AdAstra\Rules\Field\SubdivisionCodeRule;
use AdAstra\Support\Iso\Countries;
use AdAstra\Support\Iso\Subdivisions;

class StateProvince extends AbstractField
{
    protected string $handle = 'state_province';

    protected string $name = 'State/Province';

    protected array $settings_form = [
        'country' => [
            'type' => 'select',
            'label' => 'Country',
            'options' => 'countries',
            'instructions' => 'ISO 3166-1 alpha-2 code. The subdivision list shown to editors is scoped to this country.',
            'default' => 'US',
            'rules' => 'required|string|size:2',
        ],
        'default' => [
            'type' => 'text',
            'label' => 'Default Value',
            'instructions' => 'Pre-selected subdivision code, e.g. US-CA.',
            'default' => null,
            'rules' => 'nullable|string|max:10',
        ],
        'placeholder' => [
            'type' => 'text',
            'label' => 'Placeholder',
            'default' => '— Select —',
            'rules' => 'nullable|string|max:100',
        ],
        'allow_freetext_fallback' => [
            'type' => 'toggle',
            'label' => 'Allow Freetext Fallback',
            'instructions' => 'If the configured country has no subdivision data, render a text input instead of failing.',
            'default' => true,
            'rules' => 'nullable|boolean',
        ],
    ];

    public function settingsFormOptions(): array
    {
        return [
            'country' => array_map(
                fn($c) => ['value' => $c['code'], 'label' => $c['name']],
                Countries::all(),
            ),
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
            new SubdivisionCodeRule(
                country: (string)$this->getSetting('country', 'US'),
                allowFreetextFallback: (bool)$this->getSetting('allow_freetext_fallback', true),
            ),
        ];
    }

    public function prepareForStorage(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (string)$value;
    }

    public function cast(mixed $value): mixed
    {
        return $value;
    }

    /**
     * @return array{code: string, name: string, country: string}|null
     */
    public function value(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $code = (string)$raw;
        $country = (string)$this->getSetting('country', 'US');
        $name = Subdivisions::hasData($country)
            ? Subdivisions::name($country, $code)
            : $code;
        return ['code' => $code, 'name' => $name, 'country' => $country];
    }

    public function render(array $params): string
    {
        $country = (string)$this->getSetting('country', 'US');
        $params['country'] = $country;
        $params['country_name'] = Countries::name($country);
        $params['has_subdivisions'] = Subdivisions::hasData($country);
        $params['subdivision_options'] = Subdivisions::forCountry($country);
        $params['placeholder'] = (string)$this->getSetting('placeholder', '— Select —');

        return view('_fields.state_province', $params)->render();
    }
}
