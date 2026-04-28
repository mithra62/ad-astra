<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;

class RelatedItems extends AbstractCollection
{
    public $collects = RelatedItem::class;

    public function toArray(Request $request): array
    {
        return $this->collection->toArray();
    }
}
