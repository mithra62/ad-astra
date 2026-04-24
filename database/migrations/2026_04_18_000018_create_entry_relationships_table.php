<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_relationships', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entry_id')
                ->constrained('entries')
                ->cascadeOnDelete();

            $table->foreignId('related_entry_id')
                ->constrained('entries')
                ->cascadeOnDelete();

            $table->foreignId('field_id')
                ->constrained('fields')
                ->cascadeOnDelete();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            // An entry cannot be linked to the same target via the same field twice.
            $table->unique(['entry_id', 'related_entry_id', 'field_id'], 'er_entry_related_field_unique');
            $table->index(['entry_id', 'field_id'], 'er_entry_field_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_relationships');
    }
};
