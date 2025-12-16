<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Actions\Role\CreateNewRole;
use App\Actions\Actions\Role\EditRole;
use App\Http\Requests\Role\DeleteRoleRequest;
use App\Http\Requests\Role\EditRoleRequest;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Models\Role as RoleModel;
use Spatie\Permission\Models\Permission;

class Role extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $roles = RoleModel::paginate(20);
        return $this->view('roles.index', ['roles' => $roles]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $permissions = Permission::all();
        return $this->view('roles.create', ['permissions' => $permissions]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request)
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $creator = app(CreateNewRole::class);
        $role = $creator->create($request->all());
        return redirect()->route('roles.show', $role->id)->with('status', trans('role.created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $role = RoleModel::with('permissions')->find($id);
        if (!$role instanceof RoleModel) {
            abort(404);
        }

        $permissions = Permission::all();
        return $this->view('roles.edit', ['role' => $role, 'permissions' => $permissions]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EditRoleRequest $request, string $id)
    {
        $role = RoleModel::find($id);
        if ($role instanceof RoleModel) {
            $editor = app(EditRole::class);
            $editor->edit($role, $request->all());
            return redirect()->route('roles.index')->with('success', trans('role.updated'));
        }

        abort(404);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeleteRoleRequest $request, string $id)
    {
        $role = RoleModel::find($id);
        if ($role instanceof RoleModel && $role->canDelete()) {
            $role->delete();
            return redirect()->route('roles.index')->with('success', trans('role.deleted'));
        }

        return redirect()->route('roles.index')->with('failure', trans('role.not_found'));
    }

    public function confirm(string $id)
    {
        $user = RoleModel::find($id);
        if (!$user instanceof RoleModel) {
            return redirect()->route('roles.index')->with('failure', 'role.not_found');
        }

        return $this->view('roles.delete', ['role' => $user]);
    }
}
