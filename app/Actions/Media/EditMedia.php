<?php

namespace App\Actions\Media;

use App\Models\Media;

class EditMedia
{
    public function edit(Media $media, array $input): Media
    {
        return $media->update($input);
    }
}
