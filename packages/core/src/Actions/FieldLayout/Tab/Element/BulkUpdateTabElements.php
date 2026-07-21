<?php

namespace AdAstra\Actions\FieldLayout\Tab\Element;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\FieldLayout\Tab;
use AdAstra\Models\FieldLayout\TabElement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BulkUpdateTabElements extends AbstractAction
{
    public function __construct(
        private readonly EditTabElement   $editElement,
        private readonly CreateTabElement $createElement,
        private readonly DeleteTabElement $deleteElement,
    ) {
    }

    public function update(Tab $tab, array $input): Tab
    {
        $elements = $input['elements'] ?? [];
        $newFields = $input['new_fields'] ?? [];
        $removedIds = $input['removed_elements'] ?? [];

        $tabElementIds = $tab->elements()->pluck('id')->all();

        foreach ($elements as $row) {
            if (!in_array((int)$row['element_id'], $tabElementIds)) {
                throw ValidationException::withMessages([
                    'elements' => 'One or more elements do not belong to this tab.',
                ]);
            }
        }

        foreach ($removedIds as $id) {
            if (!in_array((int)$id, $tabElementIds)) {
                throw ValidationException::withMessages([
                    'removed_elements' => 'One or more removed elements do not belong to this tab.',
                ]);
            }
        }

        $layoutTabIds = Tab::where('field_layout_id', $tab->field_layout_id)->pluck('id');
        $assignedFieldIds = TabElement::whereIn('field_layout_tab_id', $layoutTabIds)->pluck('field_id')->all();
        foreach ($newFields as $row) {
            if (in_array((int)$row['field_id'], $assignedFieldIds)) {
                throw ValidationException::withMessages([
                    'new_fields' => 'One or more fields are already assigned to a tab in this layout.',
                ]);
            }
        }

        DB::transaction(function () use ($tab, $elements, $newFields, $removedIds) {
            foreach ($elements as $row) {
                $element = TabElement::find((int)$row['element_id']);
                $this->editElement->edit($element, $row);
            }

            foreach ($newFields as $row) {
                $this->createElement->create($tab, $row);
            }

            foreach ($removedIds as $id) {
                $element = TabElement::find((int)$id);
                if ($element) {
                    $this->deleteElement->delete($element);
                }
            }
        });

        return $tab->fresh(['elements.field.fieldType']);
    }
}
