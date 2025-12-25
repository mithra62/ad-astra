<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BbValue extends Model
{
    use HasFactory;
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
