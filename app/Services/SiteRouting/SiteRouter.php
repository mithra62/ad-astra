<?php

namespace App\Services\SiteRouting;

use App\Services\SiteRouting\RouteDrivers\EntryTreeRouteDriver;
use App\Services\SiteRouting\RouteDrivers\TemplateRouteDriver;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SiteRouter
{
    public function render(?string $uri): View
    {
        $result = $this->resolve($uri);

        return view('templates::' . $result->template, $result->data);
    }

    public function resolve(?string $uri): RouteResult
    {
        foreach ($this->drivers() as $driver) {
            $result = $driver->resolve($uri);

            if ($result) {
                return $result;
            }
        }

        throw new NotFoundHttpException();
    }

    protected function drivers(): array
    {
        $drivers = [];

        foreach (config('site.routing.priority', ['entry_tree', 'template']) as $driver) {
            $drivers[] = match ($driver) {
                'entry_tree' => app(EntryTreeRouteDriver::class),
                'template' => app(TemplateRouteDriver::class),
                default => throw new InvalidArgumentException("Unknown site route driver [{$driver}]."),
            };
        }

        return $drivers;
    }
}
