<?php

namespace AdAstra\Http\Controllers\Admin;

use AdAstra\Http\Controllers\Controller as DefaultController;
use Illuminate\Contracts\View\View;

abstract class Controller extends DefaultController
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->can('access admin')) {
            abort(403);
        }
    }

    /**
     * A path is always supplied, so this never returns the view Factory (which
     * the view() helper only yields for a no-argument call).
     *
     * @param string $path
     * @param array<string, mixed> $data
     * @return View
     */
    protected function view(string $path, array $data = [])
    {
        return view('admin::' . $path, $data);
    }
}
