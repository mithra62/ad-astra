<?php

namespace AdAstra\Models\FieldLayout;

use AdAstra\Models\Field;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TabElement extends Model
{
    use HasFactory;

    protected $table = 'field_layout_tab_elements';

    protected $fillable = [
        'field_layout_tab_id',
        'field_id',
        'required',
        'sort_order',
        'hidden',
        'readonly',
        'disabled',
        'schema_property',
        'label',
        'instructions',
    ];

    protected $casts = [
        'required' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tab(): BelongsTo
    {
        return $this->belongsTo(Tab::class, 'field_layout_tab_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }
}
