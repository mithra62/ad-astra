<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStatusLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'changed_by_user_id',
        'previous_status',
        'new_status',
        'previous_locked_until',
        'new_locked_until',
        'reason',
        'context',
        'created_at',
    ];

    protected $casts = [
        'previous_locked_until' => 'datetime',
        'new_locked_until'      => 'datetime',
        'context'               => 'array',
        'created_at'            => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
