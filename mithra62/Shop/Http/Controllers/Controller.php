<?php

namespace mithra62\Shop\Http\Controllers;

use App\Models\UsState;
use Illuminate\Support\Facades\Auth;
use mithra62\Shop\Settings;

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
