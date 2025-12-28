<?php
namespace App\Models\Media;

use Illuminate\Database\Eloquent\Model;

class Library extends Model
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
}
