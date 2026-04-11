<?php

namespace App\Models;

use App\Models\Field\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Field extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'instructions',
        'settings',
        'hidden',
    ];

    public function fieldable()
    {
        return $this->morphTo();
    }

    public function groups(): MorphToMany
    {
        return $this->morphedByMany(Group::class, 'fieldable')
            ->withTimestamps();
    }
}
