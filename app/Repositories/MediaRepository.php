<?php

namespace App\Repositories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class MediaRepository extends AbstractFieldableRepository
{
    /**
     * Apply a partial data payload to a Media record and persist it.
     *
     * Supported keys:
     *   name        (string)  — display name shown in the media browser
     *   sort_order  (int)     — position within the library
     *   fields      (array)   — ['field_handle' => value, ...] for any fields
     *                           defined on the owning library's field layout
     *   categories  (array)   — category IDs to sync onto the media item
     *
     * Only keys that are present in $data are written; absent keys are left
     * untouched on the existing record.
     */
    public function applyData(Media $media, array $data): Media
    {
        $this->applyCoreAttributes($media, $data);
        $media->save();

        if (array_key_exists('fields', $data)) {
            $this->applyFieldValues($media, $data['fields']);
        }

        if (array_key_exists('categories', $data)) {
            $media->categories()->sync($data['categories']);
        }

        return $media->refresh();
    }

    /**
     * Soft-delete the media record.
     *
     * Physical file removal is handled separately by the PurgeDeletedMedia job
     * so that the delete is immediately reversible and storage cleanup can be
     * batched / retried independently.
     */
    public function delete(Media $media): bool
    {
        return (bool) $media->delete();
    }

    /**
     * {@inheritdoc}
     *
     * For media items the field layout lives on the owning library.
     */
    public function resolveLayoutFields(Model $model): Collection
    {
        $model->loadMissing([
            'library.fieldLayout.tabs.elements.field.fieldType',
        ]);

        return $model->library?->fieldLayout?->fields() ?? collect();
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function applyCoreAttributes(Media $media, array $data): void
    {
        if (isset($data['name'])) {
            $media->name = $data['name'];
        }

        if (array_key_exists('sort_order', $data)) {
            $media->sort_order = (int) $data['sort_order'];
        }
    }
}
