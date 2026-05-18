<?php

namespace App\Models\Media;

use App\Traits\Field\HasFieldGroups;
use App\Traits\Field\HasFieldLayout;
use App\Traits\HasCategoryGroups;
use App\Traits\HasMediaItems;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Library extends Model
{
    use HasFactory, HasCategoryGroups, HasFieldGroups, HasFieldLayout, HasMediaItems;

    protected $table = 'media_libraries';

    protected $fillable = [
        'field_layout_id',
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
        return $this->hasMany(\App\Models\Media::class, 'library_id')
            ->orderBy('sort_order');
    }
}
