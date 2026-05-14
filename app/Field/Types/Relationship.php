<?php

namespace App\Field\Types;

use App\Field\AbstractField;
use App\Models\Entry;
use Illuminate\Support\Collection;

class Relationship extends AbstractField
{
    protected string $handle = 'relationship';

    protected string $name = 'Relationship';

    protected array $rules = [
        'array',
    ];

    protected array $settings_form = [
        'entry_group' => [
            'type' => 'select_multiple',
            'label' => 'Entry Groups',
            'options' => 'entry_groups',
            'instructions' => 'Restrict selectable entries to these groups. Leave empty to allow all.',
            'default' => [],
            'rules' => 'nullable|array'
        ],
        'entry_types' => [
            'type' => 'select_multiple',
            'label' => 'Entry Types',
            'options' => 'entry_types',
            'instructions' => 'Further restrict by entry type.',
            'default' => [],
            'rules' => 'nullable|array'
        ],
        'limit' => [
            'type' => 'number',
            'label' => 'Selection Limit',
            'instructions' => 'Maximum entries that may be selected. 0 = unlimited.',
            'default' => 0,
            'rules' => 'nullable|integer|min:0'
        ],
    ];

    public function settingsFormOptions(): array
    {
        return [
            'entry_groups' => \App\Models\EntryGroup::orderBy('name')
                ->get(['id', 'handle', 'name'])
                ->map(fn($g) => ['value' => $g->handle, 'label' => $g->name])
                ->all(),
            'entry_types' => \App\Models\EntryType::orderBy('name')
                ->get(['id', 'handle', 'name'])
                ->map(fn($t) => ['value' => $t->handle, 'label' => $t->name])
                ->all(),
        ];
    }

    /**
     * Relationship fields store data in entry_relationships, not field_values.
     * This method satisfies the abstract contract but is never called.
     */
    public function storageColumn(): string
    {
        return 'value_json';
    }

    public function isRelational(): bool
    {
        return true;
    }

    /**
     * Validate that the value is an array of IDs (or empty/null).
     *
     * @todo convert into Laravel validation rules
     */
    public function validate(mixed $value): bool|string
    {
        if ($value === null || $value === []) {
            return true;
        }

        if (!is_array($value)) {
            return 'Relationship field value must be an array of entry IDs.';
        }

        $limit = $this->getSetting('limit');
        if ($limit && count($value) > $limit) {
            return "Relationship field may not exceed {$limit} related entries.";
        }

        return true;
    }

    public function render(array $params): string
    {
        $params['entries'] = $this->fetchAvailableEntries();
        $params['selected_ids'] = $this->extractSelectedIds($params['value'] ?? null);
        $params['limit'] = (int)$this->getSetting('limit', 0);

        return view('_fields.relationship', $params)->render();
    }

    /**
     * Fetch the entries that may be selected, scoped to the configured
     * entry_group handle(s).  Returns an empty Collection when no group
     * is configured so the template can render a "nothing available" state.
     */
    private function fetchAvailableEntries(): Collection
    {
        $entryGroup = $this->getSetting('entry_group');

        if (!$entryGroup) {
            return collect();
        }

        $handles = is_array($entryGroup) ? $entryGroup : [$entryGroup];

        return Entry::query()
            ->whereHas('entryGroup', fn($q) => $q->whereIn('handle', $handles))
            ->orderBy('title')
            ->get(['id', 'title']);
    }

    /**
     * Normalise the current field value to a plain array of integer IDs so
     * the template can do simple `entry.id in selected_ids` checks.
     *
     * $value may arrive as:
     *  - a Collection of Entry models  (returned by Entry::field())
     *  - a plain array of raw IDs      (e.g. from old() flash data)
     *  - null / empty                  (no selection)
     */
    private function extractSelectedIds(mixed $value): array
    {
        if ($value instanceof Collection) {
            return $value->pluck('id')->map(fn($id) => (int)$id)->all();
        }

        if (is_array($value)) {
            return array_map('intval', $value);
        }

        return [];
    }
}
