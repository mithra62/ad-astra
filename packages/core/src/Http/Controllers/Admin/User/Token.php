<?php

namespace AdAstra\Http\Controllers\Admin\User;

use AdAstra\Actions\User\Token\CreateNewUserToken;
use AdAstra\Facades\Users;
use AdAstra\Http\Controllers\Admin\Controller as AdminController;
use AdAstra\Http\Requests\User\Token\DeleteUserTokenRequest;
use AdAstra\Http\Requests\User\Token\EditUserTokenRequest;
use AdAstra\Http\Requests\User\Token\StoreUserTokenRequest;
use AdAstra\Models\User as UserModel;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Laravel\Sanctum\PersonalAccessToken;

class Token extends AdminController
{
    /**
     * Display a listing of the resource.
     */
    public function index($id)
    {
        $user = Users::find($id);
        if (!$user instanceof UserModel) {
            return redirect()->route('users.index')->with('failure', 'user.not_found');
        }

        return $this->view('users.tokens.index', ['user' => $user]);
    }

    public function store(StoreUserTokenRequest $request, string $id)
    {
        $user = Users::find((int)$id);
        if (!$user instanceof UserModel) {
            return redirect()->route('users.index')->with('failure', 'user.not_found');
        }

        $creator = app(CreateNewUserToken::class);
        $newToken = $creator->create($user, $request->validated());

        return $this->view('users.tokens.created', ['user' => $user, 'new_token' => $newToken]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(string $id)
    {
        $user = Users::find((int)$id);
        if (!$user instanceof UserModel || !$user->can('api')) {
            return redirect()->route('users.index')->with('failure', 'user.not_found');
        }

        return $this->view('users.tokens.create', ['user' => $user]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id, string $token_id)
    {
        $user = Users::find((int)$id);
        if (!$user instanceof UserModel) {
            abort(404);
        }

        $token = Users::getToken($user, $token_id);
        if (!$token instanceof PersonalAccessToken) {
            abort(404);
        }

        return $this->view('users.tokens.edit', ['user' => $user, 'token' => $token]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EditUserTokenRequest $request, string $id, string $token_id)
    {
        $user = Users::find((int)$id);
        if (!$user instanceof UserModel) {
            abort(404);
        }

        $token = Users::updateToken($user, $token_id, $request->validated());
        if (!$token instanceof PersonalAccessToken) {
            abort(404);
        }

        return redirect()->route('users.edit', $user)->with('success', trans('user.token_updated'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeleteUserTokenRequest $request, string $id, string $token_id)
    {
        $user = Users::find((int)$id);
        if ($user instanceof UserModel) {
            Users::revokeToken($user, $token_id);
            return redirect()->route('users.edit', $user)->with('success', trans('user.token_deleted'));
        }

        abort(404);
    }

    /**
     * @param string $id
     * @param string $token_id
     * @return Factory|View|RedirectResponse
     */
    public function confirm(string $id, string $token_id)
    {
        $user = Users::find((int)$id);
        if (!$user instanceof UserModel) {
            abort(404);
        }

        $token = Users::getToken($user, $token_id);
        if (!$token instanceof PersonalAccessToken) {
            abort(404);
        }

        return $this->view('users.tokens.delete', ['user' => $user, 'token_id' => $token_id, 'token' => $token]);
    }
}
