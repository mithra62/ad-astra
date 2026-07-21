<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('mediables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->morphs('mediable'); // mediable_type, mediable_id

            // Sentinel: 0 = direct attachment (avatar, library browser pick).
            //           N = attached through a specific FileUpload field (fields.id).
            // NOT nullable — most SQL engines permit multiple NULLs in a unique
            // index, which would allow duplicate direct attachments at the DB level.
            // The sentinel value keeps the column non-null so the unique constraint
            // works correctly for all rows.
            // No FK constraint because 0 is not a valid fields.id.
            $table->unsignedBigInteger('field_id')->default(0)->index();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['media_id', 'mediable_type', 'mediable_id', 'field_id'],
                'mediables_unique'
            );
            // Note: morphs() already creates an index on (mediable_type, mediable_id).
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mediables');
    }
};
