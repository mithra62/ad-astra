<?php

namespace AdAstra\Services\SiteRouting;

use AdAstra\Services\SiteRouting\RouteDrivers\EntryTreeRouteDriver;
use AdAstra\Services\SiteRouting\RouteDrivers\TemplateRouteDriver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SiteRouter
{
    public function render(?string $uri): View|RedirectResponse
    {
        $result = $this->resolve($uri);
        if ($result->type === 'entry_tree_redirect') {
            return redirect()->away(
                $result->data['url'],
                $result->data['status'] ?? 302
            );
        }

        return view($result->template, $result->data);
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
