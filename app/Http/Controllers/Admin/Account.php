<?php

namespace App\Http\Controllers\Admin;

use App\Actions\User\UpdateUserProfileInformation;
use App\Http\Controllers\Admin\Controller as AdminController;
use App\Http\Requests\Account\EditPasswordRequest;
use App\Http\Requests\Account\EditUserRequest;
use App\Models\UserSchema;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class Account extends AdminController
{
    /**
     * @return View
     */
    public function index(): View
    {
        return $this->view('account.index');
    }

    /**
     * @return View
     */
    public function settings(): View
    {
        $schema = UserSchema::instance()->resolved();
        $user = Auth::user();
        $user->load('fieldValues.field.fieldType');
        $data = [
            'user' => $user,
            'schema' => $schema,
            'field_values' => $user->fieldArray(),
        ];
        return $this->view('account.settings', $data);
    }

    /**
     * @param EditPasswordRequest $request
     * @return RedirectResponse
     */
    public function change_password(EditPasswordRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $input = $request->validated();
        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        return redirect()->route('account.settings')->with('success', trans('account.password_changed'));
    }

    public function password()
    {
        return $this->view('account.password');
    }

    public function update(EditUserRequest $request)
    {
        $user = Auth::user();
        $editor = app(UpdateUserProfileInformation::class);
        $user = $editor->update($user, $request->validated());
        return redirect()->route('account.settings')->with('success', trans('account.updated'));
    }
}
