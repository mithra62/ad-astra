<?php

namespace App\Repositories;

use App\EntryTypes\AbstractEntryType;
use App\Models\Entry;
use App\Models\EntryRelationship;
use App\Models\EntryType as EntryTypeRecord;
use App\Models\FieldValue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EntryRepository
{
    public function create(AbstractEntryType $entryType, array $data): Entry
    {
        // Wrap core creation in a transaction so any lock acquired in beforeCreate
        // (e.g. PodcastEpisodeEntryType locking the group row) is held until the
        // new entry row is committed. afterCreate runs outside the transaction
        // so its side effects (emails, webhooks, etc.) are not rolled back.
        $entry = DB::transaction(function () use ($entryType, $data) {
            $entryType->beforeCreate($data);

            $record = $entryType->getRecord();
            $record->loadMissing(['entryGroup.statusGroup.statuses', 'entryGroup.fieldLayout', 'fieldLayout']);

            $entry = new Entry();
            $entry->entry_group_id     = $record->entry_group_id;
            $entry->entry_type_id      = $record->getKey();
            $entry->created_by_user_id = Auth::id();

            $this->applyCoreAttributes($entry, $data);
            $this->applyStatus($entry, $data['status'] ?? null, applyDefault: true);
            $entry->save();

            $this->syncAuthors($entry, $data['authors'] ?? []);
            $this->syncCategories($entry, $data['categories'] ?? []);
            $this->applyFieldValues($entry, $data['fields'] ?? []);

            return $entry->refresh();
        });

        $entryType->afterCreate($entry, $data);

        return $entry;
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
        $entryType = $entry->entryType;

        /** @var AbstractEntryType $typeObject */
        $typeObject = app(\App\EntryTypes\EntryTypeRegistry::class)->resolveByRecord($entryType);
        $typeObject->beforeUpdate($entry, $data);

        $this->applyCoreAttributes($entry, $data);

        if (array_key_exists('status', $data)) {
            $entry->loadMissing('entryGroup.statusGroup.statuses');
            $this->applyStatus($entry, $data['status'], applyDefault: false);
        }

        $entry->save();

        if (array_key_exists('authors', $data)) {
            $this->syncAuthors($entry, $data['authors']);
        }

        if (array_key_exists('categories', $data)) {
            $this->syncCategories($entry, $data['categories']);
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

    private function applyStatus(Entry $entry, ?string $handle, bool $applyDefault): void
    {
        if ($handle) {
            $entry->status = $handle;
            return;
        }

        if ($applyDefault) {
            $statusGroup = $entry->entryGroup?->statusGroup;

            if (! $statusGroup) {
                throw new \RuntimeException(
                    "EntryGroup [{$entry->entryGroup?->handle}] has no status group configured."
                );
            }

            $statusGroup->loadMissing('statuses');
            $default = $statusGroup->statuses->firstWhere('is_default', true);

            if (! $default) {
                throw new \RuntimeException(
                    "StatusGroup for EntryGroup [{$entry->entryGroup?->handle}] has no default status configured."
                );
            }

            $entry->status = $default->handle;
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

            $instance = $field->fieldType->instance();

            if ($instance->isRelational()) {
                $this->syncRelationshipField($entry, $field, (array) $value);
            } else {
                FieldValue::updateOrCreate(
                    [
                        'field_id'       => $field->getKey(),
                        'fieldable_id'   => $entry->getKey(),
                        'fieldable_type' => $entry->getMorphClass(),
                    ],
                    [$instance->storageColumn() => $value]
                );
            }
        }
    }

    private function syncRelationshipField(Entry $entry, \App\Models\Field $field, array $relatedIds): void
    {
        // Remove IDs that would create a self-reference.
        $relatedIds = array_values(array_filter(
            $relatedIds,
            fn($id) => (int) $id !== $entry->getKey()
        ));

        // Delete existing pivots for this field on this entry.
        EntryRelationship::where('entry_id', $entry->getKey())
            ->where('field_id', $field->getKey())
            ->delete();

        foreach ($relatedIds as $order => $relatedId) {
            EntryRelationship::create([
                'entry_id'         => $entry->getKey(),
                'related_entry_id' => (int) $relatedId,
                'field_id'         => $field->getKey(),
                'sort_order'       => $order,
            ]);
        }
    }

    public function resolveLayoutFields(Entry $entry): \Illuminate\Support\Collection
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
            'fieldValues.field.fieldType',
            'entryRelationships.field',
            'entryRelationships.relatedEntry',
        ];
    }
}
