<?php

namespace AdAstra\Services;

use AdAstra\Models\EntryType;

class EntryTypeService extends AbstractService
{
    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    /**
     * Create a new standalone EntryType.
     *
     * Accepted keys in $data:
     *   name              (string, required)
     *   handle            (string, required)
     *   entry_behavior_id (int|null)
     *   sort_order        (int, default 0)
     *   field_layout_id   (int|null)
     *   has_entry_tree    (bool, default false)
     *   default_template  (string|null)
     *   max_depth         (int, default 0)
     *   allowed_parent_types (array, default [])
     *
     * Group assignment is handled separately via EntryGroupService.
     */
    public function create(array $data): EntryType
    {
        return EntryType::create([
            'entry_group_id' => $data['entry_group_id'] ?? null,
            'name' => $data['name'],
            'handle' => $data['handle'],
            'entry_behavior_id' => $data['entry_behavior_id'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'field_layout_id' => $data['field_layout_id'] ?? null,
            'has_entry_tree' => $data['has_entry_tree'] ?? false,
            'default_template' => $data['default_template'] ?? null,
            'max_depth' => $data['max_depth'] ?? 0,
            'allowed_parent_types' => $data['allowed_parent_types'] ?? [],
        ]);
    }

    /**
     * Update an EntryType's attributes.
     */
    public function update(EntryType $type, array $data): EntryType
    {
        $type->update([
            'name' => $data['name'],
            'handle' => $data['handle'],
            'entry_behavior_id' => $data['entry_behavior_id'] ?? $type->entry_behavior_id,
            'sort_order' => $data['sort_order'] ?? 0,
            'field_layout_id' => $data['field_layout_id'] ?? null,
            'has_entry_tree' => $data['has_entry_tree'] ?? false,
            'default_template' => $data['default_template'] ?? null,
            'max_depth' => $data['max_depth'] ?? 0,
            'allowed_parent_types' => $data['allowed_parent_types'] ?? [],
        ]);

        return $type->fresh();
    }

    /**
     * Delete an EntryType. Associated entries cascade via DB constraint.
     */
    public function delete(EntryType $type): bool
    {
        return (bool)$type->delete();
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
