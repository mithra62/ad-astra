<?php

namespace App\Models\Field;

use App\Models\Field;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(Field::class, 'field_groups_fields')->withPivot('group_id', 'field_id');
    }
}
