<?php
namespace App\Http\Requests\User;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;

class PasswordUserRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::user()->can('edit user');
    }

    /**
     * @return string[]
     */
    public function rules(): array
    {
        return [
            'password' => 'required|min:8|confirmed',
            'password_confirmation' => 'required',
        ];
    }
}
