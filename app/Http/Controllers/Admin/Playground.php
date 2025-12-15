<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class Playground extends Controller
{
    public function index()
    {
        return view('playground.index');
    }
}
