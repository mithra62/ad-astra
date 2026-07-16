<?php

namespace AdAstra\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'User',
    title: 'User',
    description: 'A user account.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', description: 'Primary key'),
        new OA\Property(property: 'name', type: 'string', description: 'Display name'),
        new OA\Property(property: 'email', type: 'string', format: 'email', description: 'Email address (unique)'),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), nullable: true, description: 'Role names assigned to this user'),
        new OA\Property(property: 'avatar', type: 'string', nullable: true, description: 'Avatar URL'),
        new OA\Property(
            property: 'fields',
            type: 'object',
            nullable: true,
            description: 'Dynamic custom field values keyed by field handle',
            additionalProperties: new OA\AdditionalProperties(type: 'string')
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->whenLoaded('roles', fn() => $this->roles->pluck('name')->values()),
            'avatar' => $this->avatar(),
            'fields' => $this->fieldArray(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
