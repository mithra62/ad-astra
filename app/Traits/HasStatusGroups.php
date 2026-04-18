<?php

namespace App\Traits;

use App\Models\StatusGroup;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasStatusGroups
{
    public function statusGroups(): MorphToMany
    {
        return $this->morphToMany(StatusGroup::class, 'status_groupable')
            ->withTimestamps();
    }
}
