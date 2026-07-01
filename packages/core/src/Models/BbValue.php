<?php

namespace AdAstra\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BbValue extends Model
{
    use HasFactory;

    /**
     * @var string[]
     */
    protected $fillable = [
        'field_value',
        'ip_address',
        'field_name',
    ];

    /**
     * @var string
     */
    protected $table = 'bb_values';
}
