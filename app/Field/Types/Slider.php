<?php

namespace App\Field\Types;

use App\Field\AbstractField;
use App\Traits\Field\HasDecimalStorage;

class Slider extends AbstractField
{
    use HasDecimalStorage;

    protected string $handle = 'slider';

    protected string $name = 'Slider';

    protected array $settings_form = [
        'min'      => ['type' => 'number', 'label' => 'Minimum', 'instructions' => 'Lower bound of the slider.', 'default' => 0, 'rules' => 'required|numeric'],
        'max'      => ['type' => 'number', 'label' => 'Maximum', 'instructions' => 'Upper bound of the slider.', 'default' => 100, 'rules' => 'required|numeric'],
        'step'     => ['type' => 'number', 'label' => 'Step', 'default' => 1, 'rules' => 'nullable|numeric|min:0'],
        'suffix'   => ['type' => 'text', 'label' => 'Suffix', 'instructions' => 'Unit appended to displayed value, e.g. %, px, ★.', 'default' => null, 'rules' => 'nullable|string|max:20'],
        'decimals' => ['type' => 'number', 'label' => 'Decimal Places', 'instructions' => 'Set to 0 for integers. Controls storage column.', 'default' => 0, 'rules' => 'nullable|integer|min:0|max:10'],
        'default'  => ['type' => 'slider', 'label' => 'Default Value', 'instructions' => 'Initial value when no entry data exists.', 'default' => null, 'rules' => 'nullable|numeric'],
    ];

    public function validate(mixed $value): bool|string
    {
        if ($value === null || $value === '') {
            return true;
        }

        $min = $this->getSetting('min');
        $max = $this->getSetting('max');

        if ($min !== null && (float) $value < (float) $min) {
            return "Value must be at least {$min}.";
        }

        if ($max !== null && (float) $value > (float) $max) {
            return "Value must be at most {$max}.";
        }

        return true;
    }

    public function render(array $params): string
    {
        $params['min']    = $this->getSetting('min', 0);
        $params['max']    = $this->getSetting('max', 100);
        $params['step']   = $this->getSetting('step', 1);
        $params['suffix'] = $this->getSetting('suffix', '');

        return view('_fields.slider', $params)->render();
    }
}
