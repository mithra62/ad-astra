<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntryMetric extends Model
{
    use HasFactory;

    protected $table = 'entry_metrics';

    protected $fillable = [
        'entry_id',
        'metric',
        'value',
        'recorded_date',
    ];

    protected $casts = [
        'value'         => 'integer',
        'recorded_date' => 'date',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }
}
