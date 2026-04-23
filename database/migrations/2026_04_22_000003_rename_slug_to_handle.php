<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rename the `slug` column to `handle` across all application tables for
 * consistency with EntryGroup and EntryType, which already use `handle`.
 *
 * The `tags.slug` column is intentionally left untouched — it belongs to a
 * third-party package and is not part of the application's own data model.
 */
return new class extends Migration
{
    public function up(): void
    {
        // entries: composite unique (entry_group_id, slug) → (entry_group_id, handle)
        Schema::table('entries', function (Blueprint $table) {
            $table->dropUnique('entry_group_slug_unique');
            $table->renameColumn('slug', 'handle');
        });
        Schema::table('entries', function (Blueprint $table) {
            $table->unique(['entry_group_id', 'handle'], 'entry_group_handle_unique');
        });

        // categories: composite unique (group_id, slug) → (group_id, handle)
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('cat_group_slug_unique');
            $table->renameColumn('slug', 'handle');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->unique(['group_id', 'handle'], 'cat_group_handle_unique');
        });

        // fields: simple index
        Schema::table('fields', function (Blueprint $table) {
            $table->renameColumn('slug', 'handle');
        });

        // field_groups: simple index
        Schema::table('field_groups', function (Blueprint $table) {
            $table->renameColumn('slug', 'handle');
        });

        // category_groups: globally unique
        Schema::table('category_groups', function (Blueprint $table) {
            $table->renameColumn('slug', 'handle');
        });

        // media_libraries: composite unique (name, slug) → (name, handle)
        Schema::table('media_libraries', function (Blueprint $table) {
            $table->renameColumn('slug', 'handle');
        });
    }

    public function down(): void
    {
        Schema::table('media_libraries', function (Blueprint $table) {
            $table->renameColumn('handle', 'slug');
        });

        Schema::table('category_groups', function (Blueprint $table) {
            $table->renameColumn('handle', 'slug');
        });

        Schema::table('field_groups', function (Blueprint $table) {
            $table->renameColumn('handle', 'slug');
        });

        Schema::table('fields', function (Blueprint $table) {
            $table->renameColumn('handle', 'slug');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('cat_group_handle_unique');
            $table->renameColumn('handle', 'slug');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->unique(['group_id', 'slug'], 'cat_group_slug_unique');
        });

        Schema::table('entries', function (Blueprint $table) {
            $table->dropUnique('entry_group_handle_unique');
            $table->renameColumn('handle', 'slug');
        });
        Schema::table('entries', function (Blueprint $table) {
            $table->unique(['entry_group_id', 'slug'], 'entry_group_slug_unique');
        });
    }
};
