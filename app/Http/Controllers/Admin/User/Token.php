<?php

namespace App\Http\Controllers\Admin\User;

use App\Actions\Actions\User\Token\CreateNewUserToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Token\DeleteUserTokenRequest;
use App\Http\Requests\User\Token\EditUserTokenRequest;
use App\Http\Requests\User\Token\StoreUserTokenRequest;
use App\Models\User as UserModel;
use Laravel\Sanctum\PersonalAccessToken;

class Token extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        echo __FILE__ . ':' . __LINE__;
        exit;
        $users = UserModel::paginate(20);
        return view('users.index', ['users' => $users]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(string $id)
    {
        $user = UserModel::find($id);
        if (!$user instanceof UserModel || !$user->can('api')) {
            return redirect()->route('users.index')->with('failure', 'user.not_found');
        }

        return view('users.tokens.create', ['user' => $user]);
    }

    public function store(StoreUserTokenRequest $request, string $id)
    {
        $user = UserModel::find($id);
        $token = '';
        if ($user instanceof UserModel) {
            $creator = app(CreateNewUserToken::class);
            $token = $creator->create($user, $request->all())->plainTextToken;
        }

        return redirect()->route('users.edit', $user)->with('success', __('user.token_created') . ' - ' . $token);
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
        $user = UserModel::find($id);
        if (!$user instanceof UserModel) {
            abort(404);
        }

        $token = $user->tokens()->where('id', $token_id)->first();
        if(!$token instanceof PersonalAccessToken) {
            abort(404);
        }

        return view('users.tokens.edit', ['user' => $user, 'token' => $token]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EditUserTokenRequest $request, string $id, string $token_id)
    {
        $user = UserModel::find($id);
        if (!$user instanceof UserModel) {
            abort(404);
        }

        $token = $user->tokens()->where('id', $token_id)->first();
        if(!$token instanceof PersonalAccessToken) {
            abort(404);
        }

        $token->update($request->all());

        return redirect()->route('users.edit', $user)->with('success', trans('user.token_updated'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeleteUserTokenRequest $request, string $id, string $token_id)
    {
        $user = UserModel::find($id);
        if ($user instanceof UserModel) {
            $user->tokens()->where('id', $token_id)->delete();
            return redirect()->route('users.edit', $user)->with('success', trans('user.token_deleted'));
        }

        abort(404);
    }

    /**
     * @param string $id
     * @param string $token_id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function confirm(string $id, string $token_id)
    {
        $user = UserModel::find($id);
        if (!$user instanceof UserModel) {
            abort(404);
        }

        $token = $user->tokens()->where('id', $token_id)->get();
        if($token->count() !== 1) {
            abort(404);
        }

        return view('users.tokens.delete', ['user' => $user, 'token' => $token_id]);
    }
}
