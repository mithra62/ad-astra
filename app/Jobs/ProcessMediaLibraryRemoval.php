<?php

namespace App\Jobs;

use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessMediaLibraryRemoval implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(protected int $libraryId) {}

    public function handle(): void
    {
        // Soft-delete all media belonging to this library.
        // Physical file removal is handled by PurgeDeletedMedia on its next run.
        Media::where('library_id', $this->libraryId)
            ->whereNull('deleted_at')
            ->chunkById(100, function ($items) {
                foreach ($items as $media) {
                    $media->delete();
                }
            });
    }
}
