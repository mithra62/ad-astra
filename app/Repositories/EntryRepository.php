<?php

namespace App\Repositories;

use App\EntryTypes\AbstractEntryType;
use App\EntryTypes\EntryTypeRegistry;
use App\Models\Entry;
use App\Models\EntryGroup;
use App\Models\EntryRelationship;
use App\Models\Field;
use App\Models\FieldValue;
use App\Models\Status;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EntryRepository
{
    public function create(AbstractEntryType $entryType, array $data): Entry
    {
        // Wrap core creation in a transaction so any lock acquired in beforeCreate
        // (e.g. PodcastEpisodeEntryType locking the group row) is held until the
        // new entry row is committed. afterCreate runs outside the transaction
        // so its side effects (emails, webhooks, etc.) are not rolled back.
        $entry = DB::transaction(function () use ($entryType, $data) {
            $data = $entryType->beforeCreate($data);

            $record = $entryType->getRecord();
            $record->loadMissing(['entryGroup.statusGroup.statuses', 'entryGroup.fieldLayout', 'fieldLayout']);

            $entry = new Entry;
            $entry->entry_group_id = $record->entry_group_id;
            $entry->entry_type_id = $record->getKey();
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

    public function findByHandle(string $handle, string|int|EntryGroup $group): ?Entry
    {
        return Entry::with($this->defaultEagerLoad())
            ->inGroup($group)
            ->where('handle', $handle)
            ->first();
    }

    public function findOrFailByHandle(string $handle, string|int|EntryGroup $group): Entry
    {
        return Entry::with($this->defaultEagerLoad())
            ->inGroup($group)
            ->where('handle', $handle)
            ->firstOrFail();
    }

    public function applyData(Entry $entry, array $data): Entry
    {
        $entryType = $entry->entryType;

        /** @var AbstractEntryType $typeObject */
        $typeObject = app(EntryTypeRegistry::class)->resolveByRecord($entryType);
        $data = $typeObject->beforeUpdate($entry, $data);

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
        return (bool)$entry->delete();
    }

    private function applyCoreAttributes(Entry $entry, array $data): void
    {
        if (isset($data['title'])) {
            $entry->title = $data['title'];
        }

        $entry->handle = $data['handle'] ?? Str::slug($entry->title ?? '');

        if (array_key_exists('published_at', $data)) {
            $entry->published_at = $data['published_at'];
        }
    }

    private function applyStatus(Entry $entry, ?string $handle, bool $applyDefault): void
    {
        if ($handle) {
            $statusGroup = $entry->entryGroup?->statusGroup;

            if (! $statusGroup) {
                throw new \RuntimeException(
                    "EntryGroup [{$entry->entryGroup?->handle}] has no status group configured."
                );
            }

            $isValidForGroup = Status::query()
                ->where('status_group_id', $statusGroup->getKey())
                ->where('handle', $handle)
                ->exists();

            if (! $isValidForGroup) {
                throw new InvalidArgumentException(
                    "Status [{$handle}] does not belong to EntryGroup [{$entry->entryGroup?->handle}]."
                );
            }

            $entry->status = $handle;

            return;
        }

        if ($applyDefault) {
            $statusGroup = $entry->entryGroup?->statusGroup;

            if (!$statusGroup) {
                throw new \RuntimeException(
                    "EntryGroup [{$entry->entryGroup?->handle}] has no status group configured."
                );
            }

            $statusGroup->loadMissing('statuses');
            $default = $statusGroup->statuses->firstWhere('is_default', true);

            if (!$default) {
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
            $field = $layoutFields->firstWhere('handle', $handle);

            if (!$field || !$field->fieldType) {
                continue;
            }

            $instance = $field->fieldType->instance();

            if ($instance->isRelational()) {
                $this->syncRelationshipField($entry, $field, (array)$value);
            } else {
                $this->upsertFieldValue(
                    $field->getKey(),
                    $entry->getKey(),
                    $entry->getMorphClass(),
                    $instance->storageColumn(),
                    $value
                );
            }
        }
    }

    private function upsertFieldValue(
        int    $fieldId,
        int    $fieldableId,
        string $fieldableType,
        string $column,
        mixed  $value,
    ): void
    {
        $key = ['field_id' => $fieldId, 'fieldable_id' => $fieldableId, 'fieldable_type' => $fieldableType];

        try {
            FieldValue::updateOrCreate($key, [$column => $value]);
        } catch (QueryException $e) {
            // SQLSTATE 23000 = unique constraint violation: two concurrent requests
            // both saw no existing row and raced to INSERT. Retry once — the second
            // attempt will find the row the other request committed and UPDATE it.
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            FieldValue::updateOrCreate($key, [$column => $value]);
        }
    }

    private function syncRelationshipField(Entry $entry, Field $field, array $relatedIds): void
    {
        // Prevent direct self-reference (A → A). Indirect cycles (A → B → A) are
        // intentionally not enforced here — relationship data is a graph, not a tree,
        // and cycle prevention for deeper traversals must be handled by the caller
        // using loadRelatedRecursive() or an equivalent depth-limited loader.
        $relatedIds = array_values(array_filter(
            $relatedIds,
            fn($id) => (int)$id !== $entry->getKey()
        ));

        // Delete existing pivots for this field on this entry.
        EntryRelationship::where('entry_id', $entry->getKey())
            ->where('field_id', $field->getKey())
            ->delete();

        foreach ($relatedIds as $order => $relatedId) {
            EntryRelationship::create([
                'entry_id' => $entry->getKey(),
                'related_entry_id' => (int)$relatedId,
                'field_id' => $field->getKey(),
                'sort_order' => $order,
            ]);
        }
    }

    public function resolveLayoutFields(Entry $entry): Collection
    {
        $entry->loadMissing([
            'entryGroup.fieldLayout.tabs.elements.field.fieldType',
            'entryType.fieldLayout.tabs.elements.field.fieldType',
        ]);

        $groupFields = $entry->entryGroup->fieldLayout?->fields() ?? collect();
        $typeFields = $entry->entryType->fieldLayout?->fields() ?? collect();

        // Type-level fields take precedence: start with type fields, then backfill
        // group fields that don't share an ID with any type-level field.
        return $typeFields->merge($groupFields)->unique('id');
    }

    public function findMeta(int $id): ?Entry
    {
        return Entry::with($this->metaEagerLoad())->find($id);
    }

    public function findMetaOrFail(int $id): Entry
    {
        return Entry::with($this->metaEagerLoad())->findOrFail($id);
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

    private function metaEagerLoad(): array
    {
        return ['entryGroup', 'entryType', 'creator'];
    }
}
