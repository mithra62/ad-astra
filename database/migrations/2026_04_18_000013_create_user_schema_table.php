<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row table — always id=1. Owns the global FieldLayout for User fields.
        Schema::create('user_schema', function (Blueprint $table) {
            $table->id();

            $table->foreignId('field_layout_id')
                ->nullable()
                ->constrained('field_layouts')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_schema');
    }
};
