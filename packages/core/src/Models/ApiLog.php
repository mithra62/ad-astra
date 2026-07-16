<?php

namespace AdAstra\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiLog extends Model
{
    use HasFactory,
        Prunable;

    /**
     * @var string[]
     */
    protected $fillable = [
        'request_route',
        'method',
        'request_payload',
        'request_headers',
        'response_headers',
        'response_status_code',
        'user_id',
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'request_payload' => 'array',
        'request_headers' => 'array',
        'response_headers' => 'array',
        'response_status_code' => 'integer',
    ];

    /**
     * @var string
     */
    protected $table = 'api_logs';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return ApiLog
     */
    public function prunable()
    {
        return static::where('created_at', '<', now()->subDays(90));
    }
}
