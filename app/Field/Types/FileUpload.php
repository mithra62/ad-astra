<?php

namespace App\Field\Types;

use App\Contracts\SyncsToMediables;
use App\Field\AbstractField;
use App\Models\Media;
use Illuminate\Support\Collection;

class FileUpload extends AbstractField implements SyncsToMediables
{
    protected string $handle = 'file_upload';
    protected string $name = 'File Upload';

    /** @var array<string, int|null> */
    private static array $libraryHandleCache = [];

    protected array $settings_form = [
        'library' => [
            'type' => 'select_multiple',
            'label' => 'Libraries',
            'options' => 'libraries',
            'instructions' => 'Restrict uploads to specific libraries. Leave empty to allow all.',
            'default' => [],
            'rules' => 'nullable|array'
        ],
        'allowed_types' => [
            'type' => 'key_value',
            'label' => 'Allowed MIME Types',
            'instructions' => 'List of allowed MIME types (e.g. image/jpeg). Leave empty to inherit from library.',
            'default' => [],
            'rules' => 'nullable|array'
        ],
        'min' => [
            'type' => 'number',
            'label' => 'Minimum Files',
            'default' => null,
            'rules' => 'nullable|integer|min:0'
        ],
        'max' => [
            'type' => 'number',
            'label' => 'Maximum Files',
            'default' => null,
            'rules' => 'nullable|integer|min:1'
        ],
    ];

    public function settingsFormOptions(): array
    {
        return [
            'library' => \App\Models\Media\Library::orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($lib) => ['value' => $lib->id, 'label' => $lib->name])
                ->all(),
        ];
    }

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
     *
     * @todo convert into Laravel validation rules
     */
    public function validate(mixed $value): bool|string
    {
        $ids = $this->normaliseIds($value);
        $min = (int)$this->getSetting('min', 0);
        $max = $this->getSetting('max');

        if ($min > 0 && count($ids) < $min) {
            $noun = $min === 1 ? 'file' : 'files';
            return "At least {$min} {$noun} must be selected.";
        }

        if ($max !== null && count($ids) > (int)$max) {
            $noun = (int)$max === 1 ? 'file' : 'files';
            return "No more than {$max} {$noun} may be selected.";
        }

        if (empty($ids)) {
            return true;
        }

        // Verify all submitted IDs actually exist.
        $found = Media::whereIn('id', $ids)->pluck('id')->all();
        $missing = array_diff($ids, $found);
        if (!empty($missing)) {
            return 'One or more selected files no longer exist.';
        }

        // If the field is scoped to a library, verify every item belongs to it.
        $libraryId = $this->getSetting('library_id');
        if (!$libraryId && $handle = $this->getSetting('library_handle')) {
            $libraryId = $this->resolveLibraryHandle($handle);
        }

        if ($libraryId) {
            $outsideLibrary = Media::whereIn('id', $ids)
                ->where('library_id', '!=', (int)$libraryId)
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
                ->whereNotIn('mime_type', (array)$allowedTypes)
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
            ->with('fieldValues.field.fieldType')
            ->get()
            ->sortBy(fn ($m) => array_search($m->id, $ids))
            ->values();
    }

    public function render(array $params): string
    {
        $params['library_id'] = $this->resolveLibraryId();
        $params['max'] = $this->getSetting('max');
        $params['accept'] = $this->buildAcceptString();
        return view('_fields.file_upload', $params)->render();
    }

    protected function resolveLibraryId(): ?int
    {
        // New: library setting stores array of IDs from select_multiple
        $library = $this->getSetting('library');
        if (!empty($library) && is_array($library)) {
            $first = reset($library);
            if (is_numeric($first)) {
                return (int)$first;
            }
        }

        // Legacy fallbacks
        $libraryId = $this->getSetting('library_id');
        if ($libraryId) {
            return (int)$libraryId;
        }

        $handle = $this->getSetting('library_handle');
        if ($handle) {
            return $this->resolveLibraryHandle($handle);
        }

        return null;
    }

    protected function buildAcceptString(): string
    {
        $types = $this->getSetting('allowed_types', []);
        if (empty($types)) {
            return '';
        }

        $mimes = [];
        foreach ((array)$types as $entry) {
            if (is_array($entry) && !empty($entry['key'])) {
                $mimes[] = $entry['key'];
            } elseif (is_string($entry) && $entry !== '') {
                $mimes[] = $entry;
            }
        }

        return implode(',', $mimes);
    }

    private function resolveLibraryHandle(string $handle): ?int
    {
        if (!array_key_exists($handle, static::$libraryHandleCache)) {
            static::$libraryHandleCache[$handle] = \App\Models\Media\Library::where('handle', $handle)->value('id');
        }

        return static::$libraryHandleCache[$handle];
    }

    public function normaliseIds(mixed $value): array
    {
        if ($value instanceof Collection) {
            return $value->pluck('id')->map('intval')->all();
        }
        return $this->cast($value);
    }
}
