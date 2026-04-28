<?php

namespace Database\Seeders\Concerns;

use App\Models\Field;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;

/**
 * Shared helper for seeders that build FieldLayout structures.
 *
 * Extracted from EntryGroupSeeder and ExtendedEntryGroupSeeder (MED-04).
 * Any seeder that needs to scaffold layouts with tabs and field elements
 * should use this trait rather than duplicating the logic.
 */
trait BuildsLayouts
{
    /**
     * Create a FieldLayout with named tabs, each populated from field handles.
     * Fields that do not exist in the database are silently skipped so that
     * seeders remain re-runnable even when the field registry is incomplete.
     *
     * @param  array<string, string[]>  $tabs  Tab name => [field handles]
     */
    private function createLayout(string $name, array $tabs): FieldLayout
    {
        $layout   = FieldLayout::create(['name' => $name]);
        $tabOrder = 1;

        foreach ($tabs as $tabName => $fieldHandles) {
            $tab = Tab::create([
                'field_layout_id' => $layout->id,
                'name'            => $tabName,
                'sort_order'      => $tabOrder++,
            ]);

            $elementOrder = 1;
            foreach ($fieldHandles as $handle) {
                $field = Field::where('handle', $handle)->first();
                if (!$field) {
                    continue;
                }

                TabElement::create([
                    'field_layout_tab_id' => $tab->id,
                    'field_id'            => $field->id,
                    'required'            => false,
                    'sort_order'          => $elementOrder++,
                ]);
            }
        }

        return $layout;
    }

    /**
     * Add a tab (with its fields) to an existing layout only if no tab with
     * that name already exists. Safe to re-run on existing databases.
     *
     * @param  string[]  $fieldHandles
     */
    private function addTabIfMissing(int $layoutId, string $tabName, array $fieldHandles, int $sortOrder): void
    {
        $exists = Tab::where('field_layout_id', $layoutId)
            ->where('name', $tabName)
            ->exists();

        if ($exists) {
            return;
        }

        $tab = Tab::create([
            'field_layout_id' => $layoutId,
            'name'            => $tabName,
            'sort_order'      => $sortOrder,
        ]);

        $order = 1;
        foreach ($fieldHandles as $handle) {
            $field = Field::where('handle', $handle)->first();
            if (!$field) {
                continue;
            }

            TabElement::create([
                'field_layout_tab_id' => $tab->id,
                'field_id'            => $field->id,
                'required'            => false,
                'sort_order'          => $order++,
            ]);
        }
    }
}
