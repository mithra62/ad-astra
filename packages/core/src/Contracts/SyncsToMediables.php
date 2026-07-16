<?php

namespace AdAstra\Contracts;

/**
 * Marker interface for field types whose value_json column stores Media IDs
 * that should be mirrored into the `mediables` pivot table.
 *
 * FieldValueObserver detects this interface via is_subclass_of() on the
 * field type's `object` class string — no instantiation happens during boot.
 *
 * Implementers must:
 *   - return 'value_json' from storageColumn()
 *   - return an int[] of Media IDs from cast() (sort_order = array index)
 */
interface SyncsToMediables
{
}
