<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;


class Media extends BaseMedia
{
    public function media_library(): BelongsTo
    {
        return $this->belongsTo(Media\Library::class);
    }
}
