<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_layout_tabs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('field_layout_id')
                ->constrained('field_layouts')
                ->cascadeOnDelete();

            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['field_layout_id', 'sort_order'], 'flt_layout_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_layout_tabs');
    }
};
