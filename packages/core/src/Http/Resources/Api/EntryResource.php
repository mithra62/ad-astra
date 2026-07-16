<?php

namespace AdAstra\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Entry',
    title: 'Entry',
    description: 'A content entry belonging to an entry group.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', description: 'Primary key'),
        new OA\Property(property: 'entry_group_id', type: 'integer', format: 'int64', description: 'ID of the entry group this entry belongs to'),
        new OA\Property(property: 'entry_type_id', type: 'integer', format: 'int64', description: 'ID of the entry type for this entry'),
        new OA\Property(property: 'title', type: 'string', description: 'Human-readable title'),
        new OA\Property(property: 'handle', type: 'string', description: 'URL-safe identifier within the group'),
        new OA\Property(property: 'status_handle', type: 'string', nullable: true, description: 'Handle of the current status'),
        new OA\Property(property: 'status_is_public', type: 'boolean', description: 'Whether the current status is publicly visible'),
        new OA\Property(property: 'published_at', type: 'string', format: 'date-time', nullable: true, description: 'Date/time the entry was published'),
        new OA\Property(
            property: 'fields',
            type: 'object',
            nullable: true,
            description: 'Dynamic custom field values keyed by field handle',
            additionalProperties: new OA\AdditionalProperties(type: 'string')
        ),
        new OA\Property(
            property: 'authors',
            type: 'array',
            nullable: true,
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id', type: 'integer', description: 'The user ID of the author'),
                    new OA\Property(property: 'display_name', type: 'string', description: 'The author display name (falls back to username)'),
                ]
            ),
            description: 'Eligible authors assigned to this entry (included when eager-loaded)'
        ),
        new OA\Property(
            property: 'categories',
            type: 'array',
            nullable: true,
            items: new OA\Items(ref: '#/components/schemas/RelatedItem'),
            description: 'Categories assigned to this entry (included when eager-loaded)'
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class EntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entry_group_id' => $this->entry_group_id,
            'entry_type_id' => $this->entry_type_id,
            'title' => $this->title,
            'handle' => $this->handle,
            'status_handle' => $this->status_handle,
            'status_is_public' => $this->status_is_public,
            'published_at' => $this->published_at,
            'fields' => $this->fieldArray(),
            'authors' => $this->whenLoaded(
                'authors',
                fn() => $this->authors->map(fn($a) => [
                    'id' => $a->user_id,
                    'display_name' => $a->display_name,
                ])
            ),
            'categories' => $this->whenLoaded(
                'categories',
                fn() => $this->categories->map(fn($c) => ['id' => $c->id, 'title' => $c->name])
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
