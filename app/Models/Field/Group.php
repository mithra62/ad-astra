<?php

namespace App\Models\Field;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = [
        'request_route',
        'method',
        'request_payload',
        'request_headers',
        'response_payload',
        'response_headers',
        'response_status_code',
        'user_id',
    ];

    /**
     * @var string
     */
    protected $table = 'field_groups';
}
