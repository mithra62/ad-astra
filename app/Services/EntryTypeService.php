<?php

namespace App\Services;

use App\Models\EntryGroup;
use App\Models\EntryType;

class EntryTypeService extends AbstractService
{
    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    /**
     * Create a new EntryType and associate it with the given group.
     *
     * Accepted keys in $data:
     *   name            (string, required)
     *   handle          (string, required)
     *   class           (string, required) — fully-qualified AbstractEntryType subclass
     *   sort_order      (int, default 0)
     *   field_layout_id (int|null)
     *   has_entry_tree  (bool, default false)
     *   default_template (string|null)
     */
    public function create(EntryGroup|int $group, array $data): EntryType
    {
        $groupId = $group instanceof EntryGroup ? $group->getKey() : $group;

        return EntryType::create([
            'entry_group_id'  => $groupId,
            'name'            => $data['name'],
            'handle'          => $data['handle'],
            'class'           => $data['class'],
            'sort_order'      => $data['sort_order'] ?? 0,
            'field_layout_id' => $data['field_layout_id'] ?? null,
            'has_entry_tree'  => $data['has_entry_tree'] ?? false,
            'default_template' => $data['default_template'] ?? null,
        ]);
    }

    /**
     * Update an EntryType's attributes.
     */
    public function update(EntryType $type, array $data): EntryType
    {
        $type->update([
            'name'            => $data['name'],
            'handle'          => $data['handle'],
            'class'           => $data['class'],
            'sort_order'      => $data['sort_order'] ?? 0,
            'field_layout_id' => $data['field_layout_id'] ?? null,
            'has_entry_tree'  => $data['has_entry_tree'] ?? false,
            'default_template' => $data['default_template'] ?? null,
        ]);

        return $type->fresh();
    }

    /**
     * Delete an EntryType. Associated entries cascade via DB constraint.
     */
    public function delete(EntryType $type): bool
    {
        return (bool) $type->delete();
    }

    /**
     * Find an EntryType by ID. Returns null when not found.
     */
    public function find(int $id): ?EntryType
    {
        return EntryType::find($id);
    }

    /**
     * Find an EntryType by ID. Throws ModelNotFoundException when not found.
     */
    public function get(int $id): EntryType
    {
        return EntryType::findOrFail($id);
    }
}
