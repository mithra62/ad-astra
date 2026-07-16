<?php

namespace AdAstra\Traits\Category;

use AdAstra\Models\Category;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasCategories
{
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable')
            ->withTimestamps();
    }
}
