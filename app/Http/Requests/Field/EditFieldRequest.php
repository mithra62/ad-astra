<?php

namespace App\Http\Requests\Field;

use Illuminate\Support\Facades\Auth;

class EditFieldRequest extends StoreFieldRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::user()->can('edit field');
    }
}
