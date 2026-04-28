<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettingValue extends Model
{
    protected $table = 'setting_values';

    protected $fillable = [
        'domain',
        'field_handle',
        'user_id',
        'value_text',
        'value_integer',
        'value_float',
        'value_boolean',
        'value_json',
    ];

    protected $casts = [
        'user_id'       => 'integer',
        'value_integer' => 'integer',
        'value_float'   => 'float',
        'value_boolean' => 'boolean',
        'value_json'    => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
