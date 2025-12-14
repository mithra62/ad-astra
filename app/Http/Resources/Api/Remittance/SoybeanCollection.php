<?php

namespace App\Http\Resources\Api\Remittance;

use Illuminate\Http\Request;
use App\Http\Resources\Api\AbstractCollection;

class SoybeanCollection extends AbstractCollection
{
    public $collects = SoybeanResource::class;
}
