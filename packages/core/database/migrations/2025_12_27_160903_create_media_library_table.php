<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('media_libraries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('handle')->index();

            // FK to field_layouts added in a later migration — field_layouts does
            // not exist until April 2026. See 2026_05_07_000003_add_media_foreign_keys.
            $table->unsignedBigInteger('field_layout_id')->nullable()->index();

            // FK to status_groups added in a later migration — status_groups does
            // not exist until April 2026. See 2026_05_07_000003_add_media_foreign_keys.
            $table->unsignedBigInteger('status_group_id')->nullable()->index();

            $table->string('adapter', 50)->default('local');
            $table->json('adapter_settings')->nullable();
            $table->json('allowed_types')->nullable();
            $table->unsignedInteger('max_size')->default(10);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->unique('name');
            $table->unique('handle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_libraries');
    }
};
