<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiLog extends Model
{
    use HasFactory;

    /**
     * @var string[]
     */
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
    protected $table = 'api_logs';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
