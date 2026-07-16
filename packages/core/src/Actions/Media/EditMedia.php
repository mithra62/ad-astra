<?php

namespace AdAstra\Actions\Media;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\Media;
use AdAstra\Repositories\MediaRepository;

class EditMedia extends AbstractAction
{
    public function edit(Media $media, array $input): Media
    {
        return app(MediaRepository::class)->applyData($media, $input);
    }
}
