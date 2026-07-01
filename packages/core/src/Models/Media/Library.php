<?php

namespace AdAstra\Models\Media;

use AdAstra\Models\Media;
use AdAstra\Traits\Field\HasFieldLayout;
use AdAstra\Traits\HasCategoryGroups;
use AdAstra\Traits\HasMediaItems;
use AdAstra\Traits\HasStatusGroup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Library extends Model
{
    use HasFactory;
    use HasCategoryGroups;
    use HasFieldLayout;
    use HasMediaItems;
    use HasStatusGroup;

    protected $table = 'media_libraries';

    protected $fillable = [
        'field_layout_id',
        'status_group_id',
        'name',
        'handle',
        'adapter',
        'adapter_settings',
        'allowed_types',
        'max_size',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'adapter_settings' => 'array',
        'allowed_types' => 'array',
        'max_size' => 'integer',
    ];

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'library_id')
            ->orderBy('sort_order');
    }
}
