<?php

namespace AdAstra\Http\Controllers\Admin\User;

use AdAstra\Facades\Users;
use AdAstra\Http\Controllers\Admin\Controller;
use AdAstra\Http\Requests\User\UserStatusRequest;
use AdAstra\Models\User as UserModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class Status extends Controller
{
    /**
     * Change the status of a user account.
     *
     * Handles PATCH /admin/users/{id}/status.
     * Requires the 'manage user status' permission.
     */
    public function update(UserStatusRequest $request, string $id): RedirectResponse
    {
        $user = Users::find((int)$id);

        if (!$user instanceof UserModel) {
            return redirect()->route('users.index')
                ->with('failure', trans('user.not_found'));
        }

        $validated = $request->validated();

        if ($validated['status'] === \AdAstra\Enums\UserStatus::SUSPENDED) {
            Users::suspend(
                $user,
                new \DateTime($validated['suspended_until']),
                $validated['reason'] ?? '',
            );
        } else {
            Users::setStatus(
                $user,
                $validated['status'],
                $validated['reason'] ?? null,
            );
        }

        return redirect()->route('users.show', $user->id)
            ->with('success', trans('user.status_updated'));
    }

    /**
     * Remove an account lock (admin manual unlock).
     *
     * Handles DELETE /admin/users/{id}/lock.
     * Requires the 'manage user status' permission.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        if (!$request->user()->can('manage user status')) {
            abort(403);
        }

        $user = Users::find((int)$id);

        if (!$user instanceof UserModel) {
            return redirect()->route('users.index')
                ->with('failure', trans('user.not_found'));
        }

        Users::unlockUser($user);

        return redirect()->route('users.show', $user->id)
            ->with('success', trans('user.unlocked'));
    }
}
