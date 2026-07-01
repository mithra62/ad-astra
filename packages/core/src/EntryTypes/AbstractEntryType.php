<?php

namespace AdAstra\EntryTypes;

use AdAstra\Models\Entry;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryType as EntryTypeRecord;

/**
 * Base class for all entry types.
 *
 * CONTRACT: concrete subclasses MUST NOT store per-call or per-request state on
 * $this. The EntryTypeRegistry caches one instance per type for the lifetime of
 * the process (singleton), so any instance property written during a lifecycle
 * hook (beforeCreate, afterCreate, etc.) will bleed into subsequent requests.
 * Keep hooks stateless — read from $data / $entry, return results, cause side
 * effects via services, never via $this->someProperty = ….
 */
abstract class AbstractEntryType
{
    public function __construct(protected EntryTypeRecord $record)
    {
    }

    public function getRecord(): EntryTypeRecord
    {
        return $this->record;
    }

    public function getName(): string
    {
        return $this->record->name;
    }

    public function getHandle(): string
    {
        return $this->record->handle;
    }

    public function getEntryGroup(): EntryGroup
    {
        return $this->record->entryGroup;
    }

    // Lifecycle hooks — override in concrete types for custom behaviour

    public function beforeCreate(array $data): array
    {
        return $data;
    }

    public function afterCreate(Entry $entry, array $data): void
    {
    }

    public function beforeUpdate(Entry $entry, array $data): array
    {
        return $data;
    }

    public function afterUpdate(Entry $entry, array $data): void
    {
    }

    // -------------------------------------------------------------------------
    // Validation contract
    // -------------------------------------------------------------------------

    /**
     * Return field-keyed validation errors for the given data payload.
     * An empty array means the data is valid.
     *
     * Concrete types override this; the repository does NOT call it automatically.
     * Invoke from a Form Request or controller before calling create/update.
     *
     * @param array $data The same payload that would be passed to create/update.
     * @param Entry|null $entry The existing entry when validating an update; null on create.
     * @return array<string, string>  ['field_handle' => 'error message']
     */
    public function validate(array $data, ?Entry $entry = null): array
    {
        return [];
    }

    // -------------------------------------------------------------------------
    // Protected helpers for lifecycle hooks
    // -------------------------------------------------------------------------

    /**
     * Safely read a field value from an entry inside a lifecycle hook or validate().
     *
     * Accepts a nullable entry so that validate() can safely call this helper
     * in both create contexts (entry = null) and update contexts (entry present).
     * Returns null immediately when $entry is null, which callers treat as "no
     * existing value."
     *
     * Callers cannot assume that the entry passed to beforeUpdate has its
     * field relations loaded. This helper calls loadMissing() (idempotent —
     * a no-op when the relation is already loaded) before delegating to
     * $entry->field().
     */
    protected function existingFieldValue(?Entry $entry, string $handle): mixed
    {
        if ($entry === null) {
            return null;
        }

        $entry->loadMissing([
            'fieldValues.field.fieldType',
            'entryRelationships.field',
            'entryRelationships.relatedEntry',
        ]);

        return $entry->field($handle);
    }
}
