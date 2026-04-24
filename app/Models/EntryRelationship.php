<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntryRelationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_id',
        'related_entry_id',
        'field_id',
        'sort_order',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    public function relatedEntry(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'related_entry_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }
}
