<?php

namespace mithra62\Shop\Http\Controllers\Admin;

use mithra62\Shop\Actions\Actions\User\CreateNewUser;
use mithra62\Shop\Actions\Actions\User\UpdateUserPassword;
use mithra62\Shop\Http\Controllers\Controller;
use mithra62\Shop\Http\Requests\User\DeleteUserRequest;
use mithra62\Shop\Http\Requests\User\EditUserRequest;
use mithra62\Shop\Http\Requests\User\PasswordUserRequest;
use mithra62\Shop\Http\Requests\User\StoreUserRequest;
use mithra62\Shop\Models\User as UserModel;
use mithra62\Shop\Rest\Rest\Client;
use Spatie\Permission\Models\Role as RoleModel;

class User extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = UserModel::paginate(20);
        return view('users.index', ['users' => $users]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles = RoleModel::all();
        return view('users.create', ['roles' => $roles]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $creator = app(CreateNewUser::class);
        $user = $creator->create($request->all());
        return redirect()->route('users.show', $user->id)->with('status', trans('user.created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
//        $user = Auth::user();
//        $tokens = $user->tokens;
//
//        foreach ($tokens as $token) {
//            print_r($token->tokenable());
//            exit;
//        }
//
//        echo hash('sha256', '658cefa547608f38b29d3d6aeab5e220e0682f2b31c95f065e821b499894d4f2');
//        exit;

//        $user = Auth::user();
//        echo $user->createToken('MyApp 2')->plainTextToken;
//        exit;
        $client = new Client;
        $data = $client->get('remittances/corn');
        print_r($data);
        exit;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $roles = RoleModel::all();
        $user = UserModel::with('roles')->with('tokens')->find($id);
        if (!$user instanceof UserModel) {
            return redirect()->route('users.index')->with('failure', 'user.not_found');
        }

//        print_r($user);
//        exit;

        return view('users.edit', ['user' => $user, 'roles' => $roles]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EditUserRequest $request, string $id)
    {
        $user = UserModel::find($id);
        if ($user instanceof UserModel) {
            $post = $request->all();
            $user->update($post);
            $user->syncRoles($post['roles']);
            return redirect()->route('users.edit', $user)->with('success', trans('user.updated'));
        }

        return redirect()->route('users.edit', $user)->with('failure', trans('user.not_found'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeleteUserRequest $request, string $id)
    {
        $user = UserModel::find($id);
        if ($user instanceof UserModel) {
            $user->delete();
            return redirect()->route('users.index')->with('success', trans('user.deleted'));
        }

        return redirect()->route('users.index')->with('failure', trans('user.not_found'));
    }

    /**
     * @param string $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function confirm(string $id)
    {
        $user = UserModel::find($id);
        if (!$user instanceof UserModel) {
            return redirect()->route('users.index')->with('failure', 'user.not_found');
        }

        return view('users.delete', ['user' => $user]);
    }

    /**
     * @param PasswordUserRequest $request
     * @param string $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function password(PasswordUserRequest $request, string $id)
    {
        $user = UserModel::find($id);
        if (!$user instanceof UserModel) {
            return redirect()->route('users.index')->with('failure', 'user.not_found');
        }

        $password = app(UpdateUserPassword::class);
        $password->update($user, $request->all());

        return redirect()->route('users.index')->with('success', trans('user.password_changed'));
    }
}
