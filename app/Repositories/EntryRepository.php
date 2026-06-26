<?php

namespace App\Repositories;

use App\EntryTypes\AbstractEntryType;
use App\EntryTypes\EntryTypeRegistry;
use App\Models\Entry;
use App\Models\EntryAuthor;
use App\Models\EntryGroup;
use App\Models\EntryRelationship;
use App\Models\Field;
use App\Models\FieldValue;
use App\Models\Status;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

            $entry = new Entry();
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

    private function applyCoreAttributes(Entry $entry, array $data): void
    {
        if (isset($data['title'])) {
            $entry->title = $data['title'];
        }

        if (!$entry->exists && blank($data['handle'] ?? null)) {
            throw new InvalidArgumentException('Entry handle is required.');
        }

        if (array_key_exists('handle', $data)) {
            if (blank($data['handle'])) {
                throw new InvalidArgumentException('Entry handle is required.');
            }

            $entry->handle = $data['handle'];
        }

        if (array_key_exists('published_at', $data)) {
            $entry->published_at = $data['published_at'];
        }
    }

    private function applyStatus(Entry $entry, ?string $handle, bool $applyDefault): void
    {
        if ($handle) {
            $statusGroup = $entry->entryGroup?->statusGroup;

            if (!$statusGroup) {
                throw new \RuntimeException(
                    "EntryGroup [{$entry->entryGroup?->handle}] has no status group configured."
                );
            }

            $status = Status::query()
                ->where('status_group_id', $statusGroup->getKey())
                ->where('handle', $handle)
                ->first();

            if (!$status) {
                throw new InvalidArgumentException(
                    "Status [{$handle}] does not belong to EntryGroup [{$entry->entryGroup?->handle}]."
                );
            }

            $entry->status_id = $status->getKey();
            $entry->status_handle = $status->handle;
            $entry->status_is_public = $status->is_public;

            if ($status->is_public && !$entry->published_at) {
                $entry->published_at = now();
            }

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

            $entry->status_id = $default->getKey();
            $entry->status_handle = $default->handle;
            $entry->status_is_public = $default->is_public;

            if ($default->is_public && !$entry->published_at) {
                $entry->published_at = now();
            }
        }
    }

    private function syncAuthors(Entry $entry, array $userIds): void
    {
        // Resolve user IDs -> EntryAuthor IDs, filtering to active records only.
        // Ineligible user IDs are silently dropped here as a second safety net
        // (validation in the form request is the first gate).
        $authorIds = EntryAuthor::active()
            ->whereIn('user_id', $userIds)
            ->pluck('id', 'user_id');

        $sync = [];
        foreach ($userIds as $order => $userId) {
            if (isset($authorIds[$userId])) {
                $sync[$authorIds[$userId]] = ['sort_order' => $order];
            }
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

            $instance = $field->typeInstance();

            if ($instance->isRelational()) {
                $this->syncRelationshipField($entry, $field, (array)$value);
            } else {
                $this->upsertFieldValue(
                    $field->getKey(),
                    $entry->getKey(),
                    $entry->getMorphClass(),
                    $instance->storageColumn(),
                    $instance->prepareForStorage($value),
                );
            }
        }
    }

    public function resolveLayoutFields(Entry $entry): Collection
    {
        $entry->loadMissing([
            'entryGroup.fieldLayout.tabs' => fn($q) => $q->orderBy('sort_order'),
            'entryGroup.fieldLayout.tabs.elements' => fn($q) => $q->orderBy('sort_order'),
            'entryGroup.fieldLayout.tabs.elements.field.fieldType',
            'entryType.fieldLayout.tabs' => fn($q) => $q->orderBy('sort_order'),
            'entryType.fieldLayout.tabs.elements' => fn($q) => $q->orderBy('sort_order'),
            'entryType.fieldLayout.tabs.elements.field.fieldType',
        ]);

        $groupFields = $entry->entryGroup->fieldLayout?->fields() ?? collect();
        $typeFields = $entry->entryType->fieldLayout?->fields() ?? collect();

        // Type-level fields take precedence: start with type fields, then backfill
        // group fields that don't share an ID with any type-level field.
        return $typeFields->merge($groupFields)->unique('id');
    }

    private function syncRelationshipField(Entry $entry, Field $field, array $relatedIds): void
    {
        // Prevent direct self-reference (A -> A). Indirect cycles (A -> B -> A) are
        // intentionally not enforced here — relationship data is a graph, not a tree,
        // and cycle prevention for deeper traversals must be handled by the caller
        // using loadRelatedRecursive() or an equivalent depth-limited loader.
        $relatedIds = array_values(array_filter(
            $relatedIds,
            fn ($id) => (int)$id !== $entry->getKey()
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

    public function delete(Entry $entry): bool
    {
        return (bool)$entry->delete();
    }

    private function upsertFieldValue(
        int    $fieldId,
        int    $fieldableId,
        string $fieldableType,
        string $column,
        mixed  $value,
    ): void {
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

    public function findByHandle(string $handle, string|int|EntryGroup $group): ?Entry
    {
        return Entry::with($this->defaultEagerLoad())
            ->inGroup($group)
            ->where('handle', $handle)
            ->first();
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

        // Wrap all writes in a transaction — mirrors the create() pattern so a
        // failure mid-way (e.g. during applyFieldValues) cannot leave the entry
        // partially updated. afterUpdate runs outside the transaction so its
        // side effects (emails, webhooks, etc.) are not rolled back if
        // persistence fails.
        $entry = DB::transaction(function () use ($entry, $data) {
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

            return $entry->refresh();
        });

        $typeObject->afterUpdate($entry, $data);

        return $entry;
    }

    /**
     * Persist a single field value on an entry.
     *
     * Routes to scalar (FieldValue) or relational (EntryRelationship) storage
     * automatically based on the field type. Silently skips the handle if it
     * is not present in the entry's resolved layout.
     */
    public function setFieldValue(Entry $entry, string $handle, mixed $value): void
    {
        $this->applyFieldValues($entry, [$handle => $value]);
    }

    public function findMeta(int $id): ?Entry
    {
        return Entry::with($this->metaEagerLoad())->find($id);
    }

    public function find(int $id): ?Entry
    {
        return Entry::with($this->defaultEagerLoad())->find($id);
    }

    private function metaEagerLoad(): array
    {
        return ['entryGroup', 'entryType', 'creator'];
    }

    public function findMetaOrFail(int $id): Entry
    {
        return Entry::with($this->metaEagerLoad())->findOrFail($id);
    }

    public function findOrFail(int $id): Entry
    {
        return Entry::with($this->defaultEagerLoad())->findOrFail($id);
    }
}
