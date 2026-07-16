<?php

namespace AdAstra\Http\Controllers\Admin;


use AdAstra\Http\Controllers\Controller as DefaultController;
use Illuminate\Contracts\View\Factory;
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
     * @param string $path
     * @param array $data
     * @return Factory|View
     */
    protected function view(string $path, array $data = [])
    {
        return view('admin::' . $path, $data);
    }
}
