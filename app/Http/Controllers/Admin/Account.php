<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Controller AS AdminController;
use App\Http\Requests\Account\EditPasswordRequest;
use App\Http\Requests\Account\EditUserRequest;
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
        return $this->view('account.settings');
    }

    /**
     * @return View
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
        $post = $request->all();
        $user->update($post);
        return redirect()->route('account.settings')->with('success', trans('account.updated'));
    }
}
