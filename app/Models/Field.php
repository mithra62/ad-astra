<?php

namespace App\Models;

use App\Models\Field\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Field extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'description'
    ];

    public function fieldable()
    {
        return $this->morphTo();
    }

    public function groups(): MorphMany
    {
        return $this->morphMany(Group::class, 'fieldable')
            ->withTimestamps();
    }
}
