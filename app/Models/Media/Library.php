<?php
namespace App\Models\Media;

use App\Models\Category\Group;
use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Library extends Model implements HasMedia
{
    use InteractsWithMedia;

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
        'adapter_settings' => 'array',
        'allowed_types' => 'array',
        'max_size' => 'integer',
    ];

    public function category_groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'category_groups_media_library')->withPivot('group_id', 'library_id');
    }
}
