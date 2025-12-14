<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commodity extends Model
{
    /**
     * @var string[]
     */
    protected $fillable = ['id', 'title'];

    /**
     * @var string
     */
    protected $table = 'commodities';
}
