<?php

namespace AdAstra\Services\SiteRouting\RouteDrivers;

use AdAstra\Services\SiteRouting\RouteResult;

interface RouteDriverInterface
{
    public function resolve(?string $uri): ?RouteResult;
}
