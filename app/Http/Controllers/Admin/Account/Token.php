<?php

namespace App\Http\Controllers\Admin\Account;

use App\Actions\Actions\User\Token\CreateNewUserToken;
use App\Http\Controllers\Admin\Controller AS AdminController;
use App\Http\Requests\Account\Token\DeleteAccountTokenRequest;
use App\Http\Requests\Account\Token\EditAccountTokenRequest;
use App\Http\Requests\Account\Token\StoreAccountTokenRequest;
use App\Models\User as UserModel;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class Token extends AdminController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return $this->view('account.tokens.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = Auth::user();
        if (!$user->can('api')) {
            abort(404);
        }

        return $this->view('account.tokens.create', ['user' => $user]);
    }

    public function store(StoreAccountTokenRequest $request)
    {
        $user = Auth::user();
        $token = '';
        if ($user instanceof UserModel) {
            $creator = app(CreateNewUserToken::class);
            $token = $creator->create($user, $request->all())->plainTextToken;
        }

        return redirect()->route('account.tokens.index')->with('success', __('account.token_created') . ' - ' . $token);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {

        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $token_id)
    {
        $user = Auth::user();
        $token = $user->tokens()->where('id', $token_id)->first();
        if(!$token instanceof PersonalAccessToken) {
            abort(404);
        }

        return $this->view('account.tokens.edit', ['user' => $user, 'token' => $token]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EditAccountTokenRequest $request, string $token_id)
    {
        $user = Auth::user();
        $token = $user->tokens()->where('id', $token_id)->first();
        if(!$token instanceof PersonalAccessToken) {
            abort(404);
        }

        $token->update($request->all());
        return redirect()->route('account.tokens.index', $user)->with('success', trans('account.token_updated'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeleteAccountTokenRequest $request, string $token_id)
    {
        $user = Auth::user();
        if ($user instanceof UserModel) {
            $user->tokens()->where('id', $token_id)->delete();
            return redirect()->route('account.tokens.index', $user)->with('success', trans('account.token_deleted'));
        }

        abort(404);
    }

    public function confirm(string $token_id)
    {
        $user = Auth::user();
        $token = $user->tokens()->where('id', $token_id)->get();
        if($token->count() !== 1) {
            abort(404);
        }

        return $this->view('account.tokens.delete', ['token' => $token_id]);
    }
}
