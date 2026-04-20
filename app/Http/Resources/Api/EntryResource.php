<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="Entry",
 *     title="Entry Details",
 *     description="entry model",
 *     @OA\Property(property="id", type="integer", format="int64", description=""),
 *     @OA\Property(property="name", type="string", description=""),
 *     @OA\Property(property="email", type="string", description=""),
 *     @OA\Property(property="created_at", type="string", format="date-time", description=""),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="")
 * )
 */
class EntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
