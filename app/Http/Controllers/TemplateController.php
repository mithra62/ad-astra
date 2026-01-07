<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TemplateController extends Controller
{
    /**
     * Reserved first segments you don't want swallowed by template routing.
     */
    private array $reserved_groups = [
        'api', 'admin', 'login', 'logout', 'register',
        'password', 'sanctum', 'storage', 'assets', 'vendor',
    ];

    public function render(Request $request, string $group, string $template)
    {
        $this->guard($group, $template);

        $view = $this->viewName($group, $template);

        if (!View::exists($view)) {
            throw new NotFoundHttpException();
        }

        return $this->renderView($request, $view);
    }

    public function renderGroupIndex(Request $request, string $group)
    {
        $this->guard($group);

        $view = $this->viewName($group, 'index');

        if (!View::exists($view)) {
            throw new NotFoundHttpException();
        }

        return $this->renderView($request, $view);
    }

    /**
     * EE-style behavior:
     * - if {group}/{second} template exists -> render it (action template)
     * - else treat {second} as "slug" and render {group}/entry
     */
    public function renderGroupSecond(Request $request, string $group, string $second)
    {
        $this->guard($group, $second);

        // Prefer action template if it exists (EE: /blog/category)
        $actionView = $this->viewName($group, $second);
        if (View::exists($actionView)) {
            return $this->renderView($request, $actionView, [
                'slug' => null,
            ]);
        }

        // Otherwise treat second segment as entry slug (EE: /blog/my-entry)
        $entryView = $this->viewName($group, 'entry');
        if (!View::exists($entryView)) {
            throw new NotFoundHttpException();
        }

        return $this->renderView($request, $entryView, [
            'slug' => $second,
        ]);
    }

    /**
     * /{group}/{template}/{tail...}
     * Always treat as action template and pass tail segments.
     *
     * Example: /blog/category/laravel
     *  - renders blog/category
     *  - provides segment_3 = "laravel"
     */
    public function renderWithTail(Request $request, string $group, string $template, ?string $tail = null)
    {
        $this->guard($group, $template);

        $view = $this->viewName($group, $template);
        if (!View::exists($view)) {
            throw new NotFoundHttpException();
        }

        return $this->renderView($request, $view, [
            'tail' => $tail,
        ]);
    }

    private function renderView(Request $request, string $view, array $extra = [])
    {
        $segments = $request->segments();

        // segment_1, segment_2, ... like EE
        $segmentVars = [];
        foreach ($segments as $i => $seg) {
            $segmentVars['segment_' . ($i + 1)] = $seg;
        }

        // If you like EE-style key/value pairs after the template:
        // /blog/archive/year/2024/month/12 => params['year']=2024, params['month']=12
        $params = $this->segmentPairs($segments);

        return view($view, array_merge([
            'segments' => $segments,
            'params'   => $params,
            'get'      => $request->query(),
        ], $segmentVars, $extra));
    }

    private function segmentPairs(array $segments): array
    {
        // Try to interpret segments after the first 2 as key/value pairs:
        // [group, template/slug, key, val, key, val...]
        $rest = array_values(array_slice($segments, 2));

        $out = [];
        for ($i = 0; $i < count($rest); $i += 2) {
            $k = $rest[$i] ?? null;
            $v = $rest[$i + 1] ?? null;
            if ($k === null || $v === null) break;
            $out[$k] = $v;
        }

        return $out;
    }

    private function viewName(string $group, string $template): string
    {
        // TwigBridge supports dot notation: "blog.entry"
        return "templates::{$group}.{$template}";
    }

    private function guard(string $group, ?string $template = null): void
    {
        if (in_array(strtolower($group), $this->reserved_groups, true)) {
            throw new NotFoundHttpException();
        }

        foreach (array_filter([$group, $template]) as $seg) {
            // Basic path traversal prevention
            if (str_contains($seg, '..') || str_contains($seg, '/') || str_contains($seg, '\\')) {
                throw new NotFoundHttpException();
            }
        }
    }
}
