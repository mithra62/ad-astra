<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replace fully-qualified class names stored in polymorphic type columns with
 * the short morph-map aliases registered in AppServiceProvider::boot().
 *
 * This is a one-time data migration. Going forward, Eloquent writes the alias
 * automatically once Relation::morphMap() is in effect.
 */
return new class extends Migration
{
    /**
     * Map of old (class name) → new (morph alias) for each polymorphic column.
     * Format: [table, type_column, old_value, new_value]
     */
    private array $replacements = [
        // field_values.fieldable_type
        ['field_values', 'fieldable_type', 'App\\Models\\Entry',          'entry'],
        ['field_values', 'fieldable_type', 'App\\Models\\Category',       'category'],
        ['field_values', 'fieldable_type', 'App\\Models\\User',           'user'],

        // categorizables.categorizable_type
        ['categorizables', 'categorizable_type', 'App\\Models\\Entry',        'entry'],
        ['categorizables', 'categorizable_type', 'App\\Models\\Media',        'media'],

        // fieldables.fieldable_type  (Field\Group → fields pivot)
        ['fieldables', 'fieldable_type', 'App\\Models\\Field\\Group', 'field_group'],

        // field_groupables.field_groupable_type
        ['field_groupables', 'field_groupable_type', 'App\\Models\\EntryGroup',        'entry_group'],
        ['field_groupables', 'field_groupable_type', 'App\\Models\\EntryType',         'entry_type'],
        ['field_groupables', 'field_groupable_type', 'App\\Models\\Category\\Group',   'category_group'],
        ['field_groupables', 'field_groupable_type', 'App\\Models\\Media\\Library',    'media_library'],

        // category_groupables.category_groupable_type
        ['category_groupables', 'category_groupable_type', 'App\\Models\\EntryGroup',      'entry_group'],
        ['category_groupables', 'category_groupable_type', 'App\\Models\\Category\\Group', 'category_group'],
        ['category_groupables', 'category_groupable_type', 'App\\Models\\Media\\Library',  'media_library'],
    ];

    public function up(): void
    {
        foreach ($this->replacements as [$table, $column, $old, $new]) {
            DB::table($table)
                ->where($column, $old)
                ->update([$column => $new]);
        }
    }

    public function down(): void
    {
        // Reverse: swap old and new
        foreach ($this->replacements as [$table, $column, $old, $new]) {
            DB::table($table)
                ->where($column, $new)
                ->update([$column => $old]);
        }
    }
};
