<?php
namespace App\Models\Remittance;

use Illuminate\Database\Eloquent\Model;

class Meta extends Model
{
    /**
     * @var string[]
     */
    protected $fillable = [
        'remittance_id',
        'key',
        'value',
        'created_at',
        'updated_at',
    ];

    /**
     * @var string
     */
    protected $table = 'remittance_meta';
}
