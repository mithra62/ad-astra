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

    protected int $total_per_page = 10;

    public function __construct()
    {
        $this->settings = app(Settings::class);
        $this->total_per_page = $this->settings->get('general', 'items_per_page', $this->total_per_page);
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
