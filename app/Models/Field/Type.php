<?php

namespace App\Models\Field;

use Illuminate\Database\Eloquent\Model;

class Type extends Model
{
    /**
     * @var string[]
     */
    protected $fillable = [
        'name',
        'object',
        'settings'
    ];

    /**
     * @var string
     */
    protected $table = 'field_types';
}
