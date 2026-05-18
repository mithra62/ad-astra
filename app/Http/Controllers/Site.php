<?php

namespace App\Http\Controllers;

use App\Services\SiteRouting\SiteRouter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class Site extends Controller
{
    /**
     * @param SiteRouter $router
     * @param string|null $uri
     * @return View|RedirectResponse
     */
    public function show(SiteRouter $router, ?string $uri = null): View|RedirectResponse
    {
        return $router->render($uri);
    }
}
