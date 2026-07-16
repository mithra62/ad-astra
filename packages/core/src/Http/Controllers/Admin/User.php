<?php

namespace AdAstra\Http\Controllers\Admin;

use AdAstra\Actions\User\CreateNewUser;
use AdAstra\Actions\User\UpdateUserPassword;
use AdAstra\Actions\User\UpdateUserProfileInformation;
use AdAstra\Facades\Users;
use AdAstra\Http\Requests\User\DeleteUserRequest;
use AdAstra\Http\Requests\User\EditUserRequest;
use AdAstra\Http\Requests\User\PasswordUserRequest;
use AdAstra\Http\Requests\User\StoreUserRequest;
use AdAstra\Models\User as UserModel;
use AdAstra\Support\UserFieldLayout;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Spatie\Permission\Models\Role as RoleModel;

class User extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = Users::paginate(20);
        $variables = [
            'users' => $users,
            'total_users' => Users::getTotal(),
            'total_active_users' => Users::getTotal(['status' => 'active']),
            'total_super_admin' => Users::getTotalByRole('super_admin')
        ];
        return $this->view('users.index', $variables);
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
        $layout = UserFieldLayout::resolve();

        return $this->view('users.create', compact('roles', 'layout'));
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
        $user->load(['statusLogs' => fn($q) => $q->with('actor')->limit(10)]);
        $layout = UserFieldLayout::resolve();

        return $this->view('users.show', [
            'user' => $user,
            'field_values' => $user->fieldArray(),
            'layout' => $layout,
            'status_logs' => $user->statusLogs,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $layout = UserFieldLayout::resolve();
        $roles = RoleModel::all();
        $user = Users::find((int)$id);

        if (!$user instanceof UserModel) {
            return redirect()->route('users.index')->with('failure', 'user.not_found');
        }

        $user->loadMissing(['roles', 'tokens', 'fieldValues.field.fieldType']);

        return $this->view('users.edit', [
            'user' => $user,
            'roles' => $roles,
            'layout' => $layout,
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
     * @return Factory|View|RedirectResponse
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
     * @return RedirectResponse
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

    public function changePassword(string $id)
    {
        $user = Users::find((int)$id);
        if (!$user instanceof UserModel) {
            return redirect()->route('users.index')->with('failure', 'user.not_found');
        }

        return $this->view('users.password', ['user' => $user]);
    }
}
