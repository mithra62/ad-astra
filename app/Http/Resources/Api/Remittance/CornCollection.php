<?php

namespace App\Http\Resources\Api\Remittance;

use Illuminate\Http\Request;
use App\Http\Resources\Api\AbstractCollection;

class CornCollection extends AbstractCollection
{
    public $collects = CornResource::class;
}
