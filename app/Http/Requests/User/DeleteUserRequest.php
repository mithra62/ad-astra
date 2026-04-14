<?php
namespace App\Http\Requests\User;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class DeleteUserRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return (Auth::user()->id != $this->route()->parameter('user')) &&
            Auth::user()->can('delete user');
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
            'confirm_removal.required' => 'You must select at least one role.',
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
