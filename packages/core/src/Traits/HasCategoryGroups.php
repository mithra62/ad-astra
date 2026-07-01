<?php

namespace AdAstra\Traits;

use AdAstra\Models\Category\Group as CategoryGroup;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasCategoryGroups
{
    public function categoryGroups(): MorphToMany
    {
        return $this->morphToMany(CategoryGroup::class, 'category_groupable', 'category_groupables', null, 'group_id')
            ->withTimestamps();
    }
}
