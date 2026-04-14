<?php
namespace App\Http\Requests\Media\Library;

use Illuminate\Support\Facades\Auth;

class EditMediaLibraryRequest extends StoreMediaLibraryFormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('edit media library');
    }
}
