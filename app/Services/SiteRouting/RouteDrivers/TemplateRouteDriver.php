<?php

namespace App\Services\SiteRouting\RouteDrivers;

use App\Models\EntryTree;
use App\Services\SiteRouting\RouteResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class TemplateRouteDriver implements RouteDriverInterface
{
    private array $reservedGroups = [
        'api', 'admin', 'login', 'logout', 'register',
        'password', 'sanctum', 'storage', 'assets', 'vendor',
    ];

    public function __construct(
        protected Request $request
    ) {
        View::replaceNamespace('admin', []);
    }

    public function resolve(?string $uri): ?RouteResult
    {
        $uri = EntryTree::normalizeUri($uri);
        $segments = $uri === '/' ? [] : explode('/', trim($uri, '/'));

        if (empty($segments)) {
            return $this->resolveHome();
        }

        $group = $segments[0] ?? null;
        $second = $segments[1] ?? null;

        if (! $group || ! $this->isAllowed($group, $second)) {
            return null;
        }

        if (! $second) {
            return $this->resolveGroupIndex($group, $segments);
        }

        return $this->resolveGroupSecond($group, $second, $segments);
    }

    protected function resolveHome(): ?RouteResult
    {
        $view = config('site.templates.default_template', 'templates::site.index');

        if (! View::exists($view)) {
            return null;
        }

        return $this->result($view, []);
    }

    protected function resolveGroupIndex(string $group, array $segments): ?RouteResult
    {
        $view = $this->viewName($group, 'index');

        if (! View::exists($view)) {
            return null;
        }

        return $this->result($view, $segments);
    }

    protected function resolveGroupSecond(string $group, string $second, array $segments): ?RouteResult
    {
        $actionView = $this->viewName($group, $second);

        if (View::exists($actionView)) {
            return $this->result($actionView, $segments, [
                'handle' => null,
                'tail' => array_slice($segments, 2),
            ]);
        }

        $entryView = $this->viewName($group, 'entry');

        if (! View::exists($entryView)) {
            return null;
        }

        return $this->result($entryView, $segments, [
            'handle' => $second,
            'tail' => array_slice($segments, 2),
        ]);
    }

    protected function result(string $view, array $segments, array $extra = []): RouteResult
    {
        return new RouteResult(
            type: 'template',
            template: $view,
            data: array_merge(
                $this->templateData($segments),
                $extra
            ),
            resource: $view,
        );
    }

    protected function templateData(array $segments): array
    {
        $segmentVars = [];

        foreach ($segments as $i => $segment) {
            $segmentVars['segment_' . ($i + 1)] = $segment;
        }

        return array_merge([
            'segments' => $segments,
            'params' => $this->segmentPairs($segments),
            'get' => $this->request->query(),
        ], $segmentVars);
    }

    protected function segmentPairs(array $segments): array
    {
        $rest = array_values(array_slice($segments, 2));

        $params = [];

        for ($i = 0; $i < count($rest); $i += 2) {
            $key = $rest[$i] ?? null;
            $value = $rest[$i + 1] ?? null;

            if ($key === null || $value === null) {
                break;
            }

            $params[$key] = $value;
        }

        return $params;
    }

    protected function viewName(string $group, string $template): string
    {
        return "templates::{$group}.{$template}";
    }

    protected function isAllowed(string $group, ?string $template = null): bool
    {
        if (in_array(strtolower($group), $this->reservedGroups, true)) {
            return false;
        }

        foreach (array_filter([$group, $template]) as $segment) {
            if (
                str_contains($segment, '..') ||
                str_contains($segment, '/') ||
                str_contains($segment, '\\')
            ) {
                return false;
            }
        }

        return true;
    }
}
