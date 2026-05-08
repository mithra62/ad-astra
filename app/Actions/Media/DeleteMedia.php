<?php

namespace App\Actions\Media;

use App\Actions\AbstractAction;
use App\Models\Media;

class DeleteMedia extends AbstractAction
{
    public function delete(Media $media): void
    {
        app('media-service')->delete($media);
    }
}
