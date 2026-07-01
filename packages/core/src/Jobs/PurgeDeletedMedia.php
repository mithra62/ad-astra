<?php

namespace AdAstra\Jobs;

use AdAstra\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;

class PurgeDeletedMedia implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(protected int $graceDays = 30) {}

    public function handle(): void
    {
        Media::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays($this->graceDays))
            ->with('transformations')
            ->chunkById(100, function ($items) {
                foreach ($items as $media) {
                    foreach ($media->transformations as $t) {
                        if (Storage::disk($t->disk)->exists($t->path)) {
                            Storage::disk($t->disk)->delete($t->path);
                        }
                    }
                    if (Storage::disk($media->disk)->exists($media->path)) {
                        Storage::disk($media->disk)->delete($media->path);
                    }
                    $media->forceDelete();
                }
            });
    }
}
