<?php

namespace App\Models\Category;

use App\Models\Category;
use App\Traits\HasFieldGroups;
use App\Traits\HasFieldLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use HasFactory, HasFieldGroups, HasFieldLayout;

    protected $table = 'category_groups';

    protected $fillable = [
        'field_layout_id',
        'name',
        'handle',
        'sort_order',
    ];

    protected $casts = ['sort_order' => 'integer'];

    public function rootCategories(): HasMany
    {
        return $this->categories()->whereNull('parent_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'group_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
