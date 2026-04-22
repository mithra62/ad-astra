<?php

namespace App\Http\Controllers\Admin;

use App\Actions\User\CreateNewUser;
use App\Actions\User\UpdateUserPassword;
use App\Http\Requests\User\DeleteUserRequest;
use App\Http\Requests\User\EditUserRequest;
use App\Http\Requests\User\PasswordUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Models\User as UserModel;
use App\Rest\Client;
use Spatie\Permission\Models\Role as RoleModel;
use App\Models\UserSchema;

class User extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = UserModel::paginate(20);
        return $this->view('users.index', ['users' => $users]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles  = RoleModel::all();
        $schema = UserSchema::instance()->resolved();

        return $this->view('users.create', compact('roles', 'schema'));
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
        $user = UserModel::find($id);
        $user->load('fieldValues.field.fieldType');
        print_r($user->fieldArray());
        exit;
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
        $schema = UserSchema::instance()->resolved();
        $roles = RoleModel::all();
        $user = UserModel::with('roles')->with('tokens')->find($id);
        if (!$user instanceof UserModel) {
            return redirect()->route('users.index')->with('failure', 'user.not_found');
        }

        $user->load('fieldValues.field.fieldType');
        return $this->view('users.edit', ['user' => $user, 'roles' => $roles, 'schema' => $schema, 'field_values' => $user->fieldArray()]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EditUserRequest $request, string $id)
    {

        echo 'fdsa';
        exit;
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

        return $this->view('users.delete', ['user' => $user]);
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
