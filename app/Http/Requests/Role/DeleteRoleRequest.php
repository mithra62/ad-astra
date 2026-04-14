<?php
namespace App\Http\Requests\Role;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class DeleteRoleRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::user()->can('delete roles');
    }

    /**
     * @return string[]
     */
    public function rules(): array
    {
        return [
            'confirm_removal' => 'required'
        ];
    }

    /**
     * @return string[]
     */
    public function messages(): array
    {
        return [
            'confirm_removal.required' => 'You must confirm the removal',
        ];
    }

    /**
     * @return string[]
     */
    public function attributes(): array
    {
        return [
            'name' => 'full name',
            'email' => 'email address',
        ];
    }
}
