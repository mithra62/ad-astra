<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CategoryGroup',
    title: 'Category Group',
    description: 'A category group that contains categories',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', description: 'Primary key'),
        new OA\Property(property: 'name', type: 'string', description: 'Human-readable name'),
        new OA\Property(property: 'handle', type: 'string', description: 'URL-safe unique identifier'),
        new OA\Property(property: 'sort_order', type: 'integer', description: 'Display sort position'),
        new OA\Property(property: 'categories_count', type: 'integer', description: 'Total number of categories in this group'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', description: 'Creation timestamp'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', description: 'Last update timestamp'),
    ]
)]
class CategoryGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'handle'           => $this->handle,
            'sort_order'       => $this->sort_order,
            'categories_count' => $this->whenCounted('categories'),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
