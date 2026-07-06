<?php

namespace AdAstra\Http\Controllers\Api;

use AdAstra\Http\Controllers\Controller as DefaultController;
use Illuminate\Http\Request;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="Ad Astra REST API",
 *      description="API documentation for Ad Astra REST API",
 * )
 * @OA\SecurityScheme(
 *      securityScheme="sanctum",
 *      type="http",
 *      scheme="bearer",
 *      bearerFormat="Personal Access Token",
 * )
 *
 * @OA\Schema(
 *      schema="Meta",
 *      title="Collection Meta",
 *      description="",
 *      @OA\Property(property="current_page", type="integer", format="int64", description="The number of the page the results arise from"),
 *      @OA\Property(property="from", type="integer", format="int64", description=""),
 *      @OA\Property(property="last_page", type="integer", format="int64", description="The last page number for results"),
 *      @OA\Property(property="path", type="string", description="The full URL to the given results"),
 *      @OA\Property(property="per_page", type="number", format="float", description="How many results each request will contain"),
 *      @OA\Property(property="to", type="number", format="float", description=""),
 *      @OA\Property(property="total", type="number", format="float", description="The total results available")
 * )
 * @OA\Schema(
 *       schema="Links",
 *       title="Collection Links",
 *       description="Contains the links used to paginate ",
 *       @OA\Property(property="first", type="string", description="The full URL to the first page of results"),
 *       @OA\Property(property="last", type="string", description="The full URL to the last page of results"),
 *       @OA\Property(property="prev", type="string", description="The full URL to the prev page of results"),
 *       @OA\Property(property="next", type="string", description="The full URL to the next page of results")
 * )
 * @OA\Schema(
 *       schema="PaginationInfo",
 *       title="Collection Pagination Info",
 *       description="Minor details about pagination",
 *       @OA\Property(property="total_items", type="integer", format="int64", description="The total number of items available within the result set"),
 *       @OA\Property(property="items_per_page", type="integer", format="int64", description="How many items are provided per page")
 * )
 * @OA\Schema(
 *        schema="RelatedItem",
 *        title="Related Item",
 *        description="Simple details about a relationship",
 *        @OA\Property(property="id", type="integer", format="int64", description="The primary key for the item in question"),
 *        @OA\Property(property="title", type="string", description="The colloquial title"),
 *  )
 */
abstract class Controller extends DefaultController
{
    /**
     * @param Request $request
     * @return int
     */
    protected function limit(Request $request): int
    {
        $limit = (int)$request->input('limit', 10);
        if ($limit > 100) {
            $limit = 100;
        }

        return $limit;
    }

    /**
     * @param Request $request
     * @return int
     */
    protected function page(Request $request): int
    {
        return (int)$request->input('page', 1);
    }

    /**
     * @param Request $request
     * @return string
     */
    protected function sort(Request $request, array $allowed = ['id', 'created_at', 'updated_at']): string
    {
        $column = $request->input('sort', 'id');
        return in_array($column, $allowed, strict: true) ? $column : 'id';
    }

    /**
     * @param Request $request
     * @return string
     */
    protected function sortDir(Request $request): string
    {
        $dir = strtolower((string)$request->input('direction', 'asc'));
        return in_array($dir, ['asc', 'desc'], strict: true) ? $dir : 'asc';
    }

    /**
     * @param array $where
     * @param Request $request
     * @return array
     */
    protected function buildWhere(array $where, Request $request): array
    {
        if ($this->createdBefore($request)) {
            $where[] = [
                'created_at', '<=', $this->createdBefore($request),
            ];
        }

        if ($this->createdAfter($request)) {
            $where[] = [
                'created_at', '>=', $this->createdAfter($request),
            ];
        }

        return $where;
    }

    /**
     * @param Request $request
     * @return string
     */
    protected function createdBefore(Request $request): string
    {
        return $request->input('created_before', '');
    }

    /**
     * @param Request $request
     * @return string
     */
    protected function createdAfter(Request $request): string
    {
        return $request->input('created_after', '');
    }
}
