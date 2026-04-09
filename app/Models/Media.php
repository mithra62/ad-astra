<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;
use Spatie\Tags\HasTags;
use Illuminate\Database\Eloquent\Relations\MorphToMany;


class Media extends BaseMedia
{
    use HasTags;

    public function media_library(): BelongsTo
    {
        return $this->belongsTo(Media\Library::class);
    }

    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable')
            ->withTimestamps();
    }
}
