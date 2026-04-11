<?php
namespace App\Services;

use Illuminate\Foundation\Application;

abstract class AbstractService
{
    protected Application $app;

    public function __construct($app)
    {
        $this->app = $app;
    }
}
