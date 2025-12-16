<?php
namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller AS DefaultController;
abstract class Controller extends DefaultController
{
    /**
     * @param string $path
     * @param array $data
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    protected function view(string $path, array $data = [])
    {
        return view('admin.' . $path, $data);
    }
}
