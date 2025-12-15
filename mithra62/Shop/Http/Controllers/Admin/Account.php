<?php

namespace mithra62\Shop\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use mithra62\Shop\Http\Controllers\Controller;
use mithra62\Shop\Http\Requests\Account\EditPasswordRequest;
use mithra62\Shop\Http\Requests\Account\EditUserRequest;

class Account extends Controller
{
    /**
     * @return View
     */
    public function index(): View
    {
        return view('account.index');
    }

    /**
     * @return View
     */
    public function settings(): View
    {
        return view('account.settings');
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
        return view('account.password');
    }

    public function update(EditUserRequest $request)
    {
        $user = Auth::user();
        $post = $request->all();
        $user->update($post);
        return redirect()->route('account.settings')->with('success', trans('account.updated'));
    }
}
