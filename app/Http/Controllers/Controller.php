<?php

namespace App\Http\Controllers;

use App\Settings;
use Illuminate\Support\Facades\Auth;
use App\Models\UsState;

abstract class Controller
{
    /**
     * @var Settings
     */
    protected Settings $settings;

    public function __construct()
    {
        $this->settings = app('settings');
    }

    /**
     * @param string $permission
     * @return bool
     */
    protected function can(string $permission): bool
    {
        return Auth::user()->can($permission);
    }

    /**
     * @return array
     */
    protected function getPermissionStates(): array
    {
        $states = [];
        foreach(UsState::all() as $state) {
            if($this->can('read ' . strtolower($state->title))) {
                $states[] = $state->id;
            }
        }

        return $states;
    }
}
