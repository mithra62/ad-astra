<?php

namespace App\Observers;

use App\Field\Types\FileUpload;
use App\Models\FieldValue;
use Illuminate\Support\Facades\DB;

class FieldValueObserver
{
    public function saved(FieldValue $fieldValue): void
    {
        if (!$this->isFileUpload($fieldValue)) {
            return;
        }
        $this->syncMediables($fieldValue);
    }

    public function deleted(FieldValue $fieldValue): void
    {
        if (!$this->isFileUpload($fieldValue)) {
            return;
        }

        // field_id here is the actual Field ID (always > 0); never the sentinel 0.
        DB::table('mediables')
            ->where('mediable_type', $fieldValue->fieldable_type)
            ->where('mediable_id',   $fieldValue->fieldable_id)
            ->where('field_id',      $fieldValue->field_id)
            ->delete();
    }

    private function isFileUpload(FieldValue $fieldValue): bool
    {
        // Reads only the string `object` column of the already-eager-loaded
        // FieldType record — does NOT instantiate FileUpload here.
        // FileUpload::class resolves to a plain string constant at compile time,
        // so there is no circular-dependency risk during bootstrapping.
        // The actual instance() call happens in syncMediables(), well after boot.
        return $fieldValue->field?->fieldType?->object === FileUpload::class;
    }

    private function syncMediables(FieldValue $fieldValue): void
    {
        $type    = $fieldValue->fieldable_type;
        $id      = $fieldValue->fieldable_id;
        $fieldId = $fieldValue->field_id;

        $instance = $fieldValue->field->fieldType->instance();
        $newIds   = $instance->cast($fieldValue->value_json);

        // Remove stale pivot rows for this field on this model.
        DB::table('mediables')
            ->where('mediable_type', $type)
            ->where('mediable_id',   $id)
            ->where('field_id',      $fieldId)
            ->whereNotIn('media_id', $newIds ?: [0])
            ->delete();

        if (empty($newIds)) {
            return;
        }

        // Collect all rows first, then upsert in a single query.
        // A per-iteration upsert() inside foreach causes N round-trips for
        // large galleries; batching reduces that to one statement.
        $rows = [];
        foreach ($newIds as $sortOrder => $mediaId) {
            $rows[] = [
                'media_id'      => $mediaId,
                'mediable_type' => $type,
                'mediable_id'   => $id,
                'field_id'      => $fieldId,
                'sort_order'    => $sortOrder,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }

        DB::table('mediables')->upsert(
            $rows,
            ['media_id', 'mediable_type', 'mediable_id', 'field_id'],
            ['sort_order', 'updated_at']
        );
    }
}
