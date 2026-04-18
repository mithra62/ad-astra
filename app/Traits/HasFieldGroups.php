<?php

namespace App\Traits;

use App\Models\Field\Group as FieldGroup;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasFieldGroups
{
    public function fieldGroups(): MorphToMany
    {
        return $this->morphToMany(FieldGroup::class, 'field_groupable')
            ->withTimestamps();
    }
}
