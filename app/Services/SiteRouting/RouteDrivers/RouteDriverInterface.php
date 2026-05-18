<?php

namespace App\Services\SiteRouting\RouteDrivers;

use App\Services\SiteRouting\RouteResult;

interface RouteDriverInterface
{
    public function resolve(?string $uri): ?RouteResult;
}
