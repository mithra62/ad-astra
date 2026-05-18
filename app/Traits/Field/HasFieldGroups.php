<?php

namespace App\Traits\Field;

use App\Models\Field\Group as FieldGroup;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasFieldGroups
{
    public function fieldGroups(): MorphToMany
    {
        return $this->morphToMany(FieldGroup::class, 'field_groupable', 'field_groupables', null, 'group_id')
            ->withTimestamps();
    }
}
