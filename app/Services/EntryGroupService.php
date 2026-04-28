<?php

namespace App\Services;

use App\Models\EntryGroup;
use App\Models\FieldLayout;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
     *   name            (string, required)
     *   handle          (string, required)
     *   description     (string|null)
     *   sort_order      (int, default 0)
     *   status_group_id (int|null)
     *   category_groups (int[], IDs to sync)
     *   field_groups    (int[], IDs to sync)
     */
    public function create(array $data): EntryGroup
    {
        $layout = FieldLayout::create(['name' => $data['name'] . ' Entries']);

        $group = EntryGroup::create([
            'name'            => $data['name'],
            'handle'          => $data['handle'],
            'description'     => $data['description'] ?? null,
            'sort_order'      => $data['sort_order'] ?? 0,
            'status_group_id' => $data['status_group_id'] ?? null,
            'field_layout_id' => $layout->id,
        ]);

        $group->categoryGroups()->sync($data['category_groups'] ?? []);
        $group->fieldGroups()->sync($data['field_groups'] ?? []);

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
            'name'            => $data['name'],
            'handle'          => $data['handle'],
            'description'     => $data['description'] ?? null,
            'sort_order'      => $data['sort_order'] ?? 0,
            'status_group_id' => $data['status_group_id'] ?? null,
            'field_layout_id' => $data['field_layout_id'] ?? null,
        ]);

        $group->categoryGroups()->sync($data['category_groups'] ?? []);
        $group->fieldGroups()->sync($data['field_groups'] ?? []);

        return $group->fresh();
    }

    /**
     * Delete an EntryGroup. Associated entries cascade via DB constraint.
     */
    public function delete(EntryGroup $group): bool
    {
        return (bool) $group->delete();
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
}
