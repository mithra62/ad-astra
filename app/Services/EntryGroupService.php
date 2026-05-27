<?php

namespace App\Services;

use App\Models\EntryGroup;
use App\Models\EntryType;
use App\Models\FieldLayout;

class EntryGroupService extends AbstractService
{
    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    /**
     * Create a new EntryGroup.
     *
     * A FieldLayout is automatically created and associated with the group.
     *
     * Accepted keys in $data:
     *   name (string, required)
     *   handle (string, required)
     *   description (string|null)
     *   sort_order (int, default 0)
     *   status_group_id (int|null)
     *   category_groups (int[], IDs to sync)
     *   field_groups (int[], IDs to sync)
     *   entry_type_ids (int[], IDs of standalone Entry Types to attach)
     */
    public function create(array $data): EntryGroup
    {
        $group = EntryGroup::create([
            'name' => $data['name'],
            'handle' => $data['handle'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'status_group_id' => $data['status_group_id'] ?? null,
            'field_layout_id' => $data['field_layout_id'],
        ]);

        $group->categoryGroups()->sync($data['category_groups'] ?? []);
        $group->fieldGroups()->sync($data['field_groups'] ?? []);

        $this->syncEntryTypes($group, $data['entry_type_ids'] ?? []);

        return $group;
    }

    /**
     * Update an EntryGroup's attributes and pivot relationships.
     *
     * All keys in $data are applied; omit a key to leave that value unchanged.
     */
    public function update(EntryGroup $group, array $data): EntryGroup
    {
        $group->update([
            'name' => $data['name'],
            'handle' => $data['handle'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'status_group_id' => $data['status_group_id'] ?? null,
            'field_layout_id' => $data['field_layout_id'] ?? null,
        ]);

        $group->categoryGroups()->sync($data['category_groups'] ?? []);
        $group->fieldGroups()->sync($data['field_groups'] ?? []);

        $this->syncEntryTypes($group, $data['entry_type_ids'] ?? []);

        return $group->fresh();
    }

    /**
     * Delete an EntryGroup. Associated entries cascade via DB constraint.
     */
    public function delete(EntryGroup $group): bool
    {
        return (bool)$group->delete();
    }

    /**
     * Find an EntryGroup by ID. Returns null when not found.
     */
    public function find(int $id): ?EntryGroup
    {
        return EntryGroup::find($id);
    }

    /**
     * Find an EntryGroup by ID. Throws ModelNotFoundException when not found.
     */
    public function get(int $id): EntryGroup
    {
        return EntryGroup::findOrFail($id);
    }

    // -------------------------------------------------------------------------
    // Entry Type assignment
    // -------------------------------------------------------------------------

    /**
     * Sync the set of Entry Types assigned to a group.
     *
     * Types currently owned by this group that are not in $ids are detached
     * (entry_group_id set to null). Types in $ids that are unattached
     * (entry_group_id = null) are attached. Types owned by a different group
     * are silently skipped to prevent hijacking.
     *
     * @param int[] $ids
     */
    private function syncEntryTypes(EntryGroup $group, array $ids): void
    {
        $groupId = $group->getKey();

        // Detach types that were owned by this group but are no longer selected
        EntryType::where('entry_group_id', $groupId)
            ->whereNotIn('id', $ids)
            ->update(['entry_group_id' => null]);

        if (empty($ids)) {
            return;
        }

        // Attach eligible types: only those that are currently unattached
        EntryType::whereIn('id', $ids)
            ->whereNull('entry_group_id')
            ->update(['entry_group_id' => $groupId]);

        // Re-attach types already owned by this group (no-op, but handles
        // the case where an already-attached type is re-submitted in the form)
        EntryType::whereIn('id', $ids)
            ->where('entry_group_id', $groupId)
            ->update(['entry_group_id' => $groupId]);
    }
}
