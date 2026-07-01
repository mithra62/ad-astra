<?php

namespace AdAstra\Http\Controllers\Admin;

use AdAstra\Actions\User\UpdateUserProfileInformation;
use AdAstra\Http\Controllers\Admin\Controller as AdminController;
use AdAstra\Http\Requests\Account\EditPasswordRequest;
use AdAstra\Http\Requests\Account\EditUserRequest;
use AdAstra\Support\UserFieldLayout;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class Account extends AdminController
{
    public function index(): View
    {
        return $this->view('account.index');
    }

    public function details(): View
    {
        $layout = UserFieldLayout::resolve();
        $user = Auth::user();
        $user->load('fieldValues.field.fieldType');
        $data = [
            'user' => $user,
            'layout' => $layout,
            'field_values' => $user->fieldArray(),
        ];

        return $this->view('account.details', $data);
    }

    public function change_password(EditPasswordRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $input = $request->validated();
        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        return redirect()->route('account.details')->with('success', trans('account.password_changed'));
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

        return redirect()->route('account.details')->with('success', trans('account.updated'));
    }
}
