<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('field_layout_tab_elements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('field_layout_tab_id')
                ->constrained('field_layout_tabs')
                ->cascadeOnDelete();

            $table->foreignId('field_id')
                ->constrained('fields')
                ->cascadeOnDelete();

            $table->boolean('required')->default(false);
            $table->boolean('hidden')->default(false);
            $table->boolean('readonly')->default(false);
            $table->boolean('disabled')->default(false);
            $table->string('schema_property')->nullable();
            $table->string('label')->nullable();
            $table->string('instructions')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['field_layout_tab_id', 'field_id'], 'flte_tab_field_unique');
            $table->index(['field_layout_tab_id', 'sort_order'], 'flte_tab_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_layout_tab_elements');
    }
};
