<?php

namespace AdAstra\Http\Controllers\Admin;

use AdAstra\Actions\Role\CreateNewRole;
use AdAstra\Actions\Role\EditRole;
use AdAstra\Http\Requests\Role\DeleteRoleRequest;
use AdAstra\Http\Requests\Role\EditRoleRequest;
use AdAstra\Http\Requests\Role\StoreRoleRequest;
use AdAstra\Models\Role as RoleModel;
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
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request)
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $creator = app(CreateNewRole::class);
        $role = $creator->create($request->validated());
        return redirect()->route('roles.show', $role->id)->with('success', trans('role.created'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $groups = $this->buildFormRoles();
        return $this->view('roles.create', ['permissions' => $groups]);
    }

    private function buildFormRoles()
    {
        $permissions = Permission::all();
        $groups = [];
        foreach($permissions AS $permission) {
            if(!isset($groups[$permission->domain])) {
                $groups[$permission->domain] = [];
            }

            if(!in_array($permission->name, $groups[$permission->domain])) {
                $groups[$permission->domain][] = [
                    'name' => $permission->name,
                    'description' => $permission->description,
                ];
            }
        }

        ksort($groups);
        return $groups;
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EditRoleRequest $request, string $id)
    {
        $role = RoleModel::find($id);
        if ($role instanceof RoleModel) {
            $editor = app(EditRole::class);
            $editor->edit($role, $request->validated());
            return redirect()->route('roles.index')->with('success', trans('role.updated'));
        }

        abort(404);
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

        $groups = $this->buildFormRoles();
        return $this->view('roles.edit', ['role' => $role, 'permissions' => $groups]);
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
