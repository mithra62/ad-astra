<?php

namespace AdAstra\Services\SiteRouting;

class RouteResult
{
    public function __construct(
        public string $type,
        public string $template,
        public array  $data = [],
        public mixed  $resource = null,
    ) {
    }
}
