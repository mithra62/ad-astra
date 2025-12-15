<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BbValue extends Model
{
    /**
     * @var string[]
     */
    protected $fillable = [
        'field_value',
        'ip_address',
        'field_name'
    ];

    /**
     * @var string
     */
    protected $table = 'bb_values';
}
