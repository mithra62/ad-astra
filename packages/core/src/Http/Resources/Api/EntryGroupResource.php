<?php

namespace AdAstra\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'EntryGroup',
    title: 'Entry Group',
    description: 'A named collection of entries sharing a status group, field layout, and category groups.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', description: 'Primary key'),
        new OA\Property(property: 'name', type: 'string', description: 'Human-readable name'),
        new OA\Property(property: 'handle', type: 'string', description: 'URL-safe identifier (unique)'),
        new OA\Property(property: 'description', type: 'string', nullable: true, description: 'Optional description'),
        new OA\Property(property: 'sort_order', type: 'integer', nullable: true, description: 'Display sort position'),
        new OA\Property(property: 'status_group_id', type: 'integer', format: 'int64', description: 'ID of the associated status group'),
        new OA\Property(property: 'field_layout_id', type: 'integer', format: 'int64', nullable: true, description: 'ID of the associated field layout'),
        new OA\Property(property: 'entries_count', type: 'integer', nullable: true, description: 'Total number of entries in this group (present when counted)'),
        new OA\Property(property: 'entry_types_count', type: 'integer', nullable: true, description: 'Total number of entry types in this group (present when counted)'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class EntryGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'handle'             => $this->handle,
            'description'        => $this->description,
            'sort_order'         => $this->sort_order,
            'status_group_id'    => $this->status_group_id,
            'field_layout_id'    => $this->field_layout_id,
            'entries_count'      => $this->whenCounted('entries'),
            'entry_types_count'  => $this->whenCounted('entryTypes'),
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];
    }
}
