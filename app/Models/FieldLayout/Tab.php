<?php

namespace App\Models\FieldLayout;

use App\Models\FieldLayout;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tab extends Model
{
    use HasFactory;

    protected $table = 'field_layout_tabs';

    protected $fillable = [
        'field_layout_id',
        'name',
        'handle',
        'sort_order'
    ];

    protected $casts = [
        'sort_order' => 'integer'
    ];

    public function layout(): BelongsTo
    {
        return $this->belongsTo(FieldLayout::class, 'field_layout_id');
    }

    public function elements(): HasMany
    {
        return $this->hasMany(TabElement::class, 'field_layout_tab_id')
            ->orderBy('sort_order');
    }
}
