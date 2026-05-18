<?php

namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller as DefaultController;

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
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    protected function view(string $path, array $data = [])
    {
        return view('admin::' . $path, $data);
    }
}
