<?php

namespace mithra62\Shop\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

abstract class AbstractCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $return = [];
        if($this->collection) {
            $return = [
                'data' => $this->collection, // The actual collection of transformed posts
            ];
        }

        return $return;
    }

    /**
     * Customize the pagination information for the resource.
     *
     * @param  Request  $request
     * @param  array  $paginated
     * @param  array  $default
     * @return array
     */
    public function paginationInformation($request, $paginated, $default)
    {
        // Modify or add to the default pagination data
        //$default['meta']['current_page'] = $default['meta']['current_page'] + 1; // Example modification
        $default['pagination_info'] = [
            'total_items' => $default['meta']['total'],
            'items_per_page' => $default['meta']['per_page'],
        ];

        return $default;
    }
}
