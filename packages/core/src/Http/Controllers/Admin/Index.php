<?php

namespace AdAstra\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;

class Index extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect('/login');
    }
}
