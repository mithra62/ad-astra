<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // media_libraries.field_layout_id → field_layouts
        // media_libraries.status_group_id → status_groups
        // Cannot be in create_media_library_table because neither table exists
        // until April 2026.
        Schema::table('media_libraries', function (Blueprint $table) {
            $table->foreign('field_layout_id')
                ->references('id')
                ->on('field_layouts')
                ->nullOnDelete();

            $table->foreign('status_group_id')
                ->references('id')
                ->on('status_groups')
                ->nullOnDelete();
        });

        // media.status_id → statuses
        // Same reason as above — statuses does not exist until April 2026.
        Schema::table('media', function (Blueprint $table) {
            $table->foreign('status_id')
                ->references('id')
                ->on('statuses')
                ->nullOnDelete();
        });

        // NOTE: media.library_id intentionally has NO FK constraint.
        //
        // The library deletion flow is:
        //   1. Library record is deleted.
        //   2. ProcessMediaLibraryRemoval job soft-deletes media by library_id.
        //   3. PurgeDeletedMedia job removes physical files after grace period.
        //
        // A nullOnDelete() FK would null out library_id rows the moment the
        // library is deleted, causing step 2 to find nothing and leaving media
        // records permanently orphaned. A cascadeOnDelete() would hard-delete
        // media records immediately, bypassing the grace period. A plain indexed
        // column is correct for this async cleanup pattern.
    }

    public function down(): void
    {
        Schema::table('media_libraries', function (Blueprint $table) {
            $table->dropForeign(['field_layout_id']);
            $table->dropForeign(['status_group_id']);
        });

        Schema::table('media', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
        });
    }
};
