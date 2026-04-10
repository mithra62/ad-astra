<?php


namespace App\Models\Category;

use App\Models\Category;
use App\Models\Field;
use App\Models\Field\Group AS FieldGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Group extends Model
{
    use HasFactory;

    protected $table = 'category_groups';
    protected $fillable = [
        'name',
        'slug',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function category_groupable()
    {
        return $this->morphTo();
    }

    /**
     * @return MorphToMany
     */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable')
            ->withTimestamps();
    }

    /**
     * @return MorphToMany
     */
    public function field_groups(): MorphToMany
    {
        return $this->morphToMany(FieldGroup::class, 'field_groupable')
            ->withTimestamps();
    }

    /**
     * @return MorphToMany
     */
    public function fields(): MorphToMany
    {
        return $this->morphToMany(Field::class, 'fieldable')
            ->withTimestamps();
    }

    public function rootCategories(): HasMany
    {
        return $this->categories()->whereNull('parent_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
