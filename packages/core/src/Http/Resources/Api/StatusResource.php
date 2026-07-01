<?php

namespace AdAstra\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Status',
    title: 'Status',
    description: 'A status option belonging to a status group.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', description: 'Primary key'),
        new OA\Property(property: 'status_group_id', type: 'integer', format: 'int64', description: 'ID of the status group this status belongs to'),
        new OA\Property(property: 'name', type: 'string', description: 'Human-readable display name'),
        new OA\Property(property: 'handle', type: 'string', description: 'URL-safe identifier, unique within its group'),
        new OA\Property(property: 'color', type: 'string', nullable: true, description: 'Optional display colour (e.g. #ff5733)'),
        new OA\Property(property: 'is_default', type: 'boolean', description: 'Whether this is the default status in its group'),
        new OA\Property(property: 'is_public', type: 'boolean', description: 'Whether this status is publicly visible'),
        new OA\Property(property: 'sort_order', type: 'integer', description: 'Display sort position within the group'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class StatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'status_group_id' => $this->status_group_id,
            'name'            => $this->name,
            'handle'          => $this->handle,
            'color'           => $this->color,
            'is_default'      => $this->is_default,
            'is_public'       => $this->is_public,
            'sort_order'      => $this->sort_order,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
