<?php

namespace App\Traits;

use App\Models\Category\Group as CategoryGroup;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasCategoryGroups
{
    public function categoryGroups(): MorphToMany
    {
        return $this->morphToMany(CategoryGroup::class, 'category_groupable')
            ->withTimestamps();
    }
}
