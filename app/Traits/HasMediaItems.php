<?php

namespace App\Traits;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HasMediaItems
{
    /**
     * Store an uploaded file and create a Media record.
     *
     * The physical file is stored BEFORE opening the DB transaction. If the
     * insert fails after storeAs() succeeds we compensate by deleting the
     * orphaned file in the catch block. Storing inside the transaction would
     * risk leaving a file on disk with no matching DB record if the transaction
     * rolls back after the storage write completes.
     *
     * sort_order is computed inside the transaction with a write lock to prevent
     * duplicate order values under concurrent uploads.
     *
     * @throws \InvalidArgumentException when the file fails library constraints.
     */
    public function addMediaFromUpload(UploadedFile $file, array $attributes = []): Media
    {
        $errors = $this->validateUpload($file);
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        $disk = $this->adapter;
        $folder = $this->handle;
        $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();

        $path = $file->storeAs($folder, $fileName, $disk);

        try {
            return DB::transaction(function () use ($file, $disk, $fileName, $path, $attributes) {
                $nextOrder = (int)$this->media()->lockForUpdate()->max('sort_order') + 1;

                return $this->media()->create(array_merge([
                    'uuid' => (string)Str::uuid(),
                    'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                    'file_name' => $fileName,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'disk' => $disk,
                    'path' => $path,
                    'size' => $file->getSize(),
                    'sort_order' => $nextOrder,
                ], $attributes));
            });
        } catch (\Throwable $e) {
            // Compensate: remove the physical file so it doesn't become orphaned.
            Storage::disk($disk)->delete($path);
            throw $e;
        }
    }

    /**
     * Soft-delete a media record. Physical file is NOT removed here.
     * PurgeDeletedMedia handles physical cleanup after the grace period.
     */
    public function removeMedia(Media $media): void
    {
        $media->delete();
    }

    /**
     * Permanently delete a media record and its physical file.
     * Called by the purge job, or directly when immediate removal is needed.
     */
    public function purgeMedia(Media $media): void
    {
        foreach ($media->transformations as $t) {
            Storage::disk($t->disk)->delete($t->path);
        }
        Storage::disk($media->disk)->delete($media->path);
        $media->forceDelete();
    }

    /**
     * Returns human-readable validation errors. Empty array = valid.
     *
     * UploadMediaRequest also validates at the HTTP boundary; this provides a
     * second check for programmatic uploads that bypass the request layer.
     */
    public function validateUpload(UploadedFile $file): array
    {
        $errors = [];

        if ($this->max_size && $file->getSize() > ($this->max_size * 1024 * 1024)) {
            $errors[] = "File exceeds the maximum allowed size of {$this->max_size} MB.";
        }

        if (!empty($this->allowed_types)
            && !in_array($file->getMimeType(), $this->allowed_types, true)) {
            $errors[] = "File type '{$file->getMimeType()}' is not allowed in this library.";
        }

        return $errors;
    }
}
