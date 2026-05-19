<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            // FK to media_libraries is intentionally absent here — media_libraries
            // does not exist at this timestamp. A nullOnDelete FK would also race
            // ProcessMediaLibraryRemoval and orphan records. Plain indexed column
            // is correct; see 2026_05_07_000003_add_media_foreign_keys.
            $table->unsignedBigInteger('library_id')->nullable()->index();

            // FK to statuses added in a later migration — statuses does not exist
            // until April 2026. See 2026_05_07_000003_add_media_foreign_keys.
            // status_handle and status_is_public are denormalized so index-backed
            // filtering works without joining the statuses table.
            $table->unsignedBigInteger('status_id')->nullable()->index();
            $table->string('status_handle')->nullable()->index();
            $table->boolean('status_is_public')->default(false)->index();

            $table->string('name');
            $table->string('file_name');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('path');
            $table->unsignedBigInteger('size');

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
