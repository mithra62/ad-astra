<?php
namespace App\Http\Requests\Media\Library;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DeleteMediaLibraryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('delete media library');
    }

    public function rules(): array
    {
        return [
            'confirm_removal' => 'required'
        ];
    }

    public function messages(): array
    {
        return [
            'confirm_removal.required' => 'You must confirm the removal',
        ];
    }
}
