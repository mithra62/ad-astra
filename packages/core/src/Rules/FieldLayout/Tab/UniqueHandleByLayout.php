<?php

namespace AdAstra\Rules\FieldLayout\Tab;

use AdAstra\Models\FieldLayout\Tab AS TabModel;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

readonly class UniqueHandleByLayout implements ValidationRule
{
    public function __construct(private array $payload)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!empty($this->payload['tab_id'])) {
            $tab = TabModel::where('id', $this->payload['tab_id'])->first();
            if (!$tab) {
                return;
            }

            $check = TabModel::where('field_layout_id', $tab->field_layout_id)->where('handle', $value)->where('id', '!=', $tab->id)->first();
            if ($check) {
                $fail("The :attribute has already been taken.");
            }
        } elseif (!empty($this->payload['field_layout_id'])) {
            $check = TabModel::where('field_layout_id', $this->payload['field_layout_id'])->where('handle', $value)->first();
            if ($check) {
                $fail("The :attribute has already been taken.");
            }
        }
    }
}
