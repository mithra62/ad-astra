<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StatusGroup',
    title: 'Status Group',
    description: 'A named collection of statuses shared across entries or other models.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', description: 'Primary key'),
        new OA\Property(property: 'name', type: 'string', description: 'Human-readable name'),
        new OA\Property(property: 'handle', type: 'string', description: 'URL-safe unique identifier'),
        new OA\Property(property: 'sort_order', type: 'integer', description: 'Display sort position'),
        new OA\Property(property: 'statuses_count', type: 'integer', description: 'Total number of statuses in this group'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class StatusGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'handle'         => $this->handle,
            'sort_order'     => $this->sort_order,
            'statuses_count' => $this->whenCounted('statuses'),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
