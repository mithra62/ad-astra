<?php

namespace AdAstra\Actions\Media;

use AdAstra\Actions\AbstractAction;
use AdAstra\Facades\MediaStorage;
use AdAstra\Models\Media;

class DeleteMedia extends AbstractAction
{
    public function delete(Media $media): void
    {
        MediaStorage::delete($media);
    }
}
