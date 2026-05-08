<?php

namespace App\Field\Types;

use App\Field\AbstractField;
use App\Models\Media;
use Illuminate\Support\Collection;

class FileUpload extends AbstractField
{
    protected string $handle = 'file_upload';
    protected string $name   = 'File Upload';

    public function storageColumn(): string
    {
        return 'value_json';
    }

    public function isRelational(): bool
    {
        return false;
    }

    /**
     * Validate the submitted value against field settings.
     *
     * Checks (in order):
     *   1. Minimum / maximum file count
     *   2. All submitted IDs exist in the media table
     *   3. If a library is configured, all items belong to that library
     *   4. Field-level allowed_types override (MIME check)
     */
    public function validate(mixed $value): bool|string
    {
        $ids = $this->normaliseIds($value);
        $min = (int) $this->getSetting('min', 0);
        $max = $this->getSetting('max');

        if ($min > 0 && count($ids) < $min) {
            $noun = $min === 1 ? 'file' : 'files';
            return "At least {$min} {$noun} must be selected.";
        }

        if ($max !== null && count($ids) > (int) $max) {
            $noun = (int) $max === 1 ? 'file' : 'files';
            return "No more than {$max} {$noun} may be selected.";
        }

        if (empty($ids)) {
            return true;
        }

        // Verify all submitted IDs actually exist.
        $found   = Media::whereIn('id', $ids)->pluck('id')->all();
        $missing = array_diff($ids, $found);
        if (!empty($missing)) {
            return 'One or more selected files no longer exist.';
        }

        // If the field is scoped to a library, verify every item belongs to it.
        $libraryId = $this->getSetting('library_id');
        if (!$libraryId && $handle = $this->getSetting('library_handle')) {
            $libraryId = once(fn () =>
                \App\Models\Media\Library::where('handle', $handle)->value('id')
            );
        }

        if ($libraryId) {
            $outsideLibrary = Media::whereIn('id', $ids)
                ->where('library_id', '!=', (int) $libraryId)
                ->pluck('id')
                ->all();
            if (!empty($outsideLibrary)) {
                return 'One or more selected files do not belong to the expected library.';
            }
        }

        // Field-level MIME type restriction (overrides the library setting).
        $allowedTypes = $this->getSetting('allowed_types');
        if (!empty($allowedTypes)) {
            $badType = Media::whereIn('id', $ids)
                ->whereNotIn('mime_type', (array) $allowedTypes)
                ->exists();
            if ($badType) {
                return 'One or more selected files have a disallowed file type.';
            }
        }

        return true;
    }

    /** Cast raw stored value to a plain array of integer IDs. */
    public function cast(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? array_map('intval', $decoded) : [];
        }
        if (is_array($value)) {
            return array_map('intval', $value);
        }
        return [];
    }

    /**
     * Resolve stored IDs to Media models, preserving saved sort order.
     *
     * FieldValue::resolvedValue() calls this so $entry->field('gallery')
     * returns Collection<Media> rather than raw IDs.
     *
     * Note: FieldValue casts value_json to array, so $raw arrives here as a
     * PHP array (already decoded), not a JSON string.
     */
    public function value(mixed $raw): Collection
    {
        $ids = $this->cast($raw);
        if (empty($ids)) {
            return collect();
        }
        return Media::whereIn('id', $ids)
            ->get()
            ->sortBy(fn ($m) => array_search($m->id, $ids))
            ->values();
    }

    public function normaliseIds(mixed $value): array
    {
        if ($value instanceof Collection) {
            return $value->pluck('id')->map('intval')->all();
        }
        return $this->cast($value);
    }
}
