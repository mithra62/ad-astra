<?php

namespace AdAstra\Field\Types;

use AdAstra\Contracts\SyncsToMediables;
use AdAstra\Field\AbstractField;
use AdAstra\Models\Media as MediaModel;
use AdAstra\Models\Media\Library;
use Illuminate\Support\Collection;

/**
 * Media field type — upload new files and/or pick from existing Media.
 *
 * Storage matches FileUpload: an int[] of Media IDs in field_values.value_json.
 * FieldValueObserver mirrors those IDs into the `mediables` pivot via the
 * SyncsToMediables marker.
 *
 * Field config selects the in-scope Libraries; each Library's own settings
 * (allowed_types, max_size) govern what may be uploaded or browsed.
 */
class Media extends AbstractField implements SyncsToMediables
{
    protected string $handle = 'media';
    protected string $name = 'Media';

    protected array $settings_form = [
        'libraries' => [
            'type' => 'select_multiple',
            'label' => 'Libraries',
            'options' => 'libraries',
            'instructions' => 'One or more Libraries this field can pick from and upload to. At least one is required.',
            'default' => [],
            'rules' => 'required|array|min:1',
        ],
        'min' => [
            'type' => 'number',
            'label' => 'Minimum Items',
            'default' => null,
            'rules' => 'nullable|integer|min:0',
        ],
        'max' => [
            'type' => 'number',
            'label' => 'Maximum Items',
            'default' => null,
            'rules' => 'nullable|integer|min:1',
        ],
    ];

    public function settingsFormOptions(): array
    {
        return [
            'libraries' => Library::orderBy('name')
                ->get(['id', 'name'])
                ->map(fn($lib) => ['value' => $lib->id, 'label' => $lib->name])
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
     * Validate the submitted value:
     *   1. Minimum / maximum item count
     *   2. All submitted IDs exist in the media table
     *   3. Every selected Media's library_id is in the field's allowed libraries
     */
    public function validate(mixed $value): bool|string
    {
        $ids = $this->cast($value);
        $min = (int)$this->getSetting('min', 0);
        $max = $this->getSetting('max');

        if ($min > 0 && count($ids) < $min) {
            $noun = $min === 1 ? 'item' : 'items';
            return "At least {$min} {$noun} must be selected.";
        }

        if ($max !== null && count($ids) > (int)$max) {
            $noun = (int)$max === 1 ? 'item' : 'items';
            return "No more than {$max} {$noun} may be selected.";
        }

        if (empty($ids)) {
            return true;
        }

        $found = MediaModel::whereIn('id', $ids)->pluck('id')->all();
        $missing = array_diff($ids, $found);
        if (!empty($missing)) {
            return 'One or more selected items no longer exist.';
        }

        $allowedLibraries = $this->normaliseLibraryIds($this->getSetting('libraries', []));
        if (!empty($allowedLibraries)) {
            $outside = MediaModel::whereIn('id', $ids)
                ->whereNotIn('library_id', $allowedLibraries)
                ->exists();
            if ($outside) {
                return 'One or more selected items do not belong to an allowed library.';
            }
        }

        return true;
    }

    /**
     * Resolve stored IDs to Media models, preserving saved sort order.
     *
     * FieldValue::resolvedValue() calls this so $entry->field('gallery')
     * returns Collection<Media> rather than raw IDs.
     */
    public function value(mixed $raw): Collection
    {
        $ids = $this->cast($raw);
        if (empty($ids)) {
            return collect();
        }

        return MediaModel::whereIn('id', $ids)
            ->with('fieldValues.field.fieldType')
            ->get()
            ->sortBy(fn($m) => array_search($m->id, $ids))
            ->values();
    }

    public function render(array $params): string
    {
        $libraryIds = $this->normaliseLibraryIds($this->getSetting('libraries', []));

        $libraries = !empty($libraryIds)
            ? Library::whereIn('id', $libraryIds)->orderBy('name')->get()
            : collect();

        // Most admin views (users/edit, categories/edit, entries via
        // _schema-tab-elements, etc.) do not pass `input_name` to render(),
        // so default it from the field's handle. Without this fallback the
        // hidden inputs end up named `[]` and the field key is missing from
        // the submitted payload — making any "required" check fire even when
        // media is selected.
        $handle = $this->field?->handle;
        $params['input_name'] = $params['input_name'] ?? ($handle ? 'fields[' . $handle . ']' : 'media');

        // After a validation error Laravel flashes the submitted input. Restore
        // the user's just-selected media IDs so the chip strip re-renders with
        // their work intact and they don't have to re-pick or re-upload.
        if ($handle && ($oldIds = old('fields.' . $handle)) !== null && is_array($oldIds)) {
            $params['value'] = $this->value($oldIds);
        }

        $params['libraries'] = $libraries;
        $params['library_ids'] = $libraries->pluck('id')->all();
        $params['min'] = $this->getSetting('min');
        $params['max'] = $this->getSetting('max');
        $params['picker_url'] = route('media.picker.index');

        return view('_fields.media', $params)->render();
    }

    /** @return int[] */
    private function normaliseLibraryIds(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_filter(array_map('intval', $value)));
    }
}
