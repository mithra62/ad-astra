<?php

namespace AdAstra\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Generic {id, title} shape for a related record. Not bound to a single model,
 * so the two attributes it reads are declared rather than mixed in.
 *
 * @property int $id
 * @property string $title
 */
class RelatedItem extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
        ];
    }
}
