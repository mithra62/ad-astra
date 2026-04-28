<?php

namespace App\Http\Requests\Entry\Type;

use App\EntryTypes\AbstractEntryType;
use App\Http\Requests\FormRequest;
use App\Rules\ExtendsClass;
use Illuminate\Support\Facades\Auth;

class EditEntryTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('edit entry type');
    }

    public function rules(): array
    {
        return [
            'id' => ['required'],
            'name' => ['required', 'string', 'max:255'],
            'handle' => ['required', 'string', 'max:255'],
            'class' => ['required', 'string', 'max:255', new ExtendsClass(AbstractEntryType::class)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'field_layout_id' => ['nullable', 'integer', 'exists:field_layouts,id'],
        ];
    }
}
