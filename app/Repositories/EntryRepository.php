<?php

namespace App\Repositories;

use App\EntryTypes\AbstractEntryType;
use App\Models\Entry;
use App\Models\EntryStatus;
use App\Models\EntryType as EntryTypeRecord;
use App\Models\Field;
use App\Models\FieldValue;
use App\Models\Status;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class EntryRepository
{
    public function create(AbstractEntryType $entryType, array $data): Entry
    {
        $entryType->beforeCreate($data);

        $record = $entryType->getRecord();
        $record->loadMissing(['entryGroup.statusGroups.statuses', 'entryGroup.fieldLayout', 'fieldLayout']);

        $entry = new Entry();
        $entry->entry_group_id     = $record->entry_group_id;
        $entry->entry_type_id      = $record->getKey();
        $entry->created_by_user_id = Auth::id();

        $this->applyCoreAttributes($entry, $data);
        $entry->save();

        $this->syncAuthors($entry, $data['authors'] ?? []);
        $this->syncCategories($entry, $data['categories'] ?? []);
        $this->applyStatuses($entry, $data['statuses'] ?? [], applyDefaults: true);
        $this->applyFieldValues($entry, $data['fields'] ?? []);

        $entryType->afterCreate($entry, $data);

        return $entry->refresh();
    }

    public function findOrFail(int $id): Entry
    {
        return Entry::with($this->defaultEagerLoad())->findOrFail($id);
    }

    public function find(int $id): ?Entry
    {
        return Entry::with($this->defaultEagerLoad())->find($id);
    }

    public function applyData(Entry $entry, array $data): Entry
    {
        $entryType = $entry->entryType->loadMissing(['entryGroup.statusGroups.statuses']);

        /** @var AbstractEntryType $typeObject */
        $typeObject = app(\App\EntryTypes\EntryTypeRegistry::class)->resolveByRecord($entryType);
        $typeObject->beforeUpdate($entry, $data);

        $this->applyCoreAttributes($entry, $data);
        $entry->save();

        if (array_key_exists('authors', $data)) {
            $this->syncAuthors($entry, $data['authors']);
        }

        if (array_key_exists('categories', $data)) {
            $this->syncCategories($entry, $data['categories']);
        }

        if (array_key_exists('statuses', $data)) {
            $this->applyStatuses($entry, $data['statuses'], applyDefaults: false);
        }

        if (array_key_exists('fields', $data)) {
            $this->applyFieldValues($entry, $data['fields']);
        }

        $typeObject->afterUpdate($entry, $data);

        return $entry->refresh();
    }

    public function delete(Entry $entry): bool
    {
        return (bool) $entry->delete();
    }

    private function applyCoreAttributes(Entry $entry, array $data): void
    {
        if (isset($data['title'])) {
            $entry->title = $data['title'];
        }

        $entry->slug = $data['slug'] ?? Str::slug($entry->title ?? '');

        if (array_key_exists('published_at', $data)) {
            $entry->published_at = $data['published_at'];
        }
    }

    private function syncAuthors(Entry $entry, array $userIds): void
    {
        $sync = [];
        foreach ($userIds as $order => $userId) {
            $sync[$userId] = ['sort_order' => $order];
        }

        $entry->authors()->sync($sync);
    }

    private function syncCategories(Entry $entry, array $categoryIds): void
    {
        $entry->categories()->sync($categoryIds);
    }

    private function applyStatuses(Entry $entry, array $statuses, bool $applyDefaults): void
    {
        $statusGroups = $entry->entryGroup->statusGroups->loadMissing('statuses');

        foreach ($statusGroups as $group) {
            $handle = $statuses[$group->handle] ?? null;

            if ($handle) {
                $status = $group->statuses->firstWhere('handle', $handle);
            } elseif ($applyDefaults) {
                $status = $group->statuses->firstWhere('is_default', true);
            } else {
                continue;
            }

            if (! $status) {
                continue;
            }

            EntryStatus::updateOrCreate(
                ['entry_id' => $entry->getKey(), 'status_group_id' => $group->getKey()],
                ['status_id' => $status->getKey()]
            );
        }
    }

    private function applyFieldValues(Entry $entry, array $fields): void
    {
        if (empty($fields)) {
            return;
        }

        $layoutFields = $this->resolveLayoutFields($entry);

        foreach ($fields as $handle => $value) {
            $field = $layoutFields->firstWhere('slug', $handle);

            if (! $field || ! $field->fieldType) {
                continue;
            }

            $column = $field->fieldType->instance()->storageColumn();

            FieldValue::updateOrCreate(
                [
                    'field_id'       => $field->getKey(),
                    'fieldable_id'   => $entry->getKey(),
                    'fieldable_type' => Entry::class,
                ],
                [$column => $value]
            );
        }
    }

    private function resolveLayoutFields(Entry $entry): \Illuminate\Support\Collection
    {
        $entry->loadMissing([
            'entryGroup.fieldLayout.tabs.elements.field.fieldType',
            'entryType.fieldLayout.tabs.elements.field.fieldType',
        ]);

        $groupFields = $entry->entryGroup->fieldLayout?->fields() ?? collect();
        $typeFields  = $entry->entryType->fieldLayout?->fields() ?? collect();

        return $groupFields->merge($typeFields)->unique('id');
    }

    private function defaultEagerLoad(): array
    {
        return [
            'entryGroup',
            'entryType',
            'creator',
            'authors',
            'categories',
            'entryStatuses.statusGroup',
            'entryStatuses.status',
            'fieldValues.field.fieldType',
        ];
    }
}
