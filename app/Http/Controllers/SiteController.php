<?php

namespace App\Http\Controllers;

use App\Services\SiteRouting\SiteRouter;
use Illuminate\Contracts\View\View;

class SiteController extends Controller
{
    public function show(SiteRouter $router, ?string $uri = null): View
    {
        return $router->render($uri);
    }
}
