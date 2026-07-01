<?php

namespace AdAstra\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Category',
    title: 'Category',
    description: 'A category belonging to a category group. Categories support hierarchical nesting via parent_id and carry optional dynamic field values defined by the group\'s field layout.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', description: 'Primary key'),
        new OA\Property(property: 'group_id', type: 'integer', format: 'int64', description: 'ID of the category group this category belongs to'),
        new OA\Property(property: 'parent_id', type: 'integer', format: 'int64', nullable: true, description: 'ID of the parent category, or null for root categories'),
        new OA\Property(property: 'name', type: 'string', description: 'Human-readable display name'),
        new OA\Property(property: 'handle', type: 'string', description: 'URL-safe unique identifier within the group'),
        new OA\Property(property: 'sort_order', type: 'integer', description: 'Display sort position among siblings'),
        new OA\Property(
            property: 'fields',
            type: 'object',
            nullable: true,
            description: 'Dynamic custom field values keyed by field handle. The available field handles and their value types are defined by the category group\'s field layout.',
        ),
        new OA\Property(
            property: 'children',
            type: 'array',
            description: 'Immediate child categories, included only when the children relation is loaded (i.e. on show requests)',
            items: new OA\Items(ref: '#/components/schemas/Category'),
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', description: 'Creation timestamp'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', description: 'Last update timestamp'),
    ]
)]
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'group_id'   => $this->group_id,
            'parent_id'  => $this->parent_id,
            'name'       => $this->name,
            'handle'     => $this->handle,
            'sort_order' => $this->sort_order,
            'fields'     => $this->fieldArray(),
            'children'   => self::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
