<?php
namespace App\Models;

use App\Models\Category\Group;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;
use Spatie\Tags\HasTags;


class Media extends BaseMedia
{
    use HasTags;

    public function media_library(): BelongsTo
    {
        return $this->belongsTo(Media\Library::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_media')->withPivot('category_id', 'media_id');
    }
}
