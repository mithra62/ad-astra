<?php

namespace App\Http\Controllers;

use App\Settings;
use Illuminate\Support\Facades\Auth;

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
}
