<?php

namespace App\Actions\Media;

use App\Actions\AbstractAction;
use App\Models\Media;
use App\Repositories\MediaRepository;

class EditMedia extends AbstractAction
{
    public function edit(Media $media, array $input): Media
    {
        return app(MediaRepository::class)->applyData($media, $input);
    }
}
