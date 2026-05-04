<?php

namespace App\Http\Controllers\Admin;

use App\Actions\User\CreateNewUser;
use App\Actions\User\UpdateUserPassword;
use App\Actions\User\UpdateUserProfileInformation;
use App\Facades\Users;
use App\Http\Requests\User\DeleteUserRequest;
use App\Http\Requests\User\EditUserRequest;
use App\Http\Requests\User\PasswordUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Models\User as UserModel;
use App\Models\UserSchema;
use Spatie\Permission\Models\Role as RoleModel;

class User extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = Users::paginate(20);
        return $this->view('users.index', ['users' => $users]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $creator = app(CreateNewUser::class);
        $user = $creator->create($request->validated());
        return redirect()->route('users.show', $user->id)->with('success', trans('user.created'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles = RoleModel::all();
        $schema = UserSchema::instance()->resolved();

        return $this->view('users.create', compact('roles', 'schema'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = Users::find((int)$id);
        if (!$user instanceof UserModel) {
            return redirect()->route('users.index')->with('failure', 'user.not_found');
        }

        $user->loadMissing(['roles', 'tokens', 'fieldValues.field.fieldType']);
        $schema = UserSchema::instance()->resolved();
        return $this->view('users.show', [
            'user' => $user,
            'field_values' => $user->fieldArray(),
            'schema' => $schema,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $schema = UserSchema::instance()->resolved();
        $roles = RoleModel::all();
        $user = Users::find((int)$id);

        if (!$user instanceof UserModel) {
            return redirect()->route('users.index')->with('failure', 'user.not_found');
        }

        $user->loadMissing(['roles', 'tokens', 'fieldValues.field.fieldType']);

        return $this->view('users.edit', [
            'user' => $user,
            'roles' => $roles,
            'schema' => $schema,
            'field_values' => $user->fieldArray(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeleteUserRequest $request, string $id)
    {
        $user = Users::find((int)$id);
        if ($user instanceof UserModel) {
            Users::delete($user);
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
        $user = Users::find((int)$id);
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
        $user = Users::find((int)$id);
        if (!$user instanceof UserModel) {
            return redirect()->route('users.index')->with('failure', 'user.not_found');
        }

        $password = app(UpdateUserPassword::class);
        $password->update($user, $request->validated());

        return redirect()->route('users.show', $user)->with('success', trans('user.password_changed'));
    }

    public function changePassword(string $id)
    {
        $user = Users::find((int)$id);
        if (!$user instanceof UserModel) {
            return redirect()->route('users.index')->with('failure', 'user.not_found');
        }

        return $this->view('users.password', ['user' => $user]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EditUserRequest $request, string $id)
    {
        $user = Users::find((int)$id);
        if ($user instanceof UserModel) {
            $editor = app(UpdateUserProfileInformation::class);
            $user = $editor->update($user, $request->validated());
            return redirect()->route('users.edit', $user)->with('success', trans('user.updated'));
        }

        return redirect()->route('users.edit', $id)->with('failure', trans('user.not_found'));
    }
}
