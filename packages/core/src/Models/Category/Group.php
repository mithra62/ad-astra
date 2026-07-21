<?php

namespace AdAstra\Models\Category;

use AdAstra\Models\Category;
use AdAstra\Traits\Field\HasFieldLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use HasFactory;
    use HasFieldLayout;

    protected $table = 'category_groups';

    protected $fillable = [
        'field_layout_id',
        'name',
        'handle',
        'description',
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
