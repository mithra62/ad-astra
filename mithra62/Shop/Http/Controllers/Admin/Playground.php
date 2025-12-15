<?php
namespace mithra62\Shop\Http\Controllers\Admin;

use mithra62\Shop\Http\Controllers\Controller;

class Playground extends Controller
{
    public function index()
    {
        return view('playground.index');
    }
}
