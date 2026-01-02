<?php
namespace App\Models\Media;

use App\Models\Category\Group;
use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Library extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'adapter',
        'adapter_settings',
        'server_path',
        'url',
        'allowed_types',
        'max_size',
        'sort_order',
    ];

    protected $table = 'media_libraries';

    protected $casts = [
        'sort_order' => 'integer',
        'adapter_settings' => 'array'
    ];

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function category_groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'category_groups_media_library')->withPivot('group_id', 'library_id');
    }
}
