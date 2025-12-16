<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Category extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        echo __FILE__ . ': '. __LINE__;
        exit;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        echo __FILE__ . ': '. __LINE__;
        exit;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        echo __FILE__ . ': '. __LINE__;
        exit;
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        echo __FILE__ . ': '. __LINE__;
        exit;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        echo __FILE__ . ': '. __LINE__;
        exit;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        echo __FILE__ . ': '. __LINE__;
        exit;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        echo __FILE__ . ': '. __LINE__;
        exit;
    }
}
