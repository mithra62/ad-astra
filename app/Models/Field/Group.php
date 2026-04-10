<?php

namespace App\Models\Field;

use App\Models\Field;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Group extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description'
    ];

    /**
     * @var string
     */
    protected $table = 'field_groups';

    public function field_groupable()
    {
        return $this->morphTo();
    }

    /**
     * @return MorphToMany
     */
    public function fields(): MorphToMany
    {
        return $this->morphToMany(Field::class, 'fieldable')
            ->withTimestamps();
    }
}
