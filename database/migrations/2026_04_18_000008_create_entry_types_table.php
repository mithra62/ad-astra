<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entry_types', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entry_group_id')
                ->constrained('entry_groups')
                ->cascadeOnDelete();

            $table->foreignId('field_layout_id')
                ->nullable()
                ->constrained('field_layouts')
                ->nullOnDelete();

            $table->string('name');
            $table->string('handle');
            $table->string('default_schema_type')->nullable();
            $table->string('default_template')->nullable();

            $table->boolean('has_entry_tree')->default(false);
            $table->unsignedInteger('max_depth')->nullable();
            $table->json('allowed_parent_types')->nullable();

            $table->string('class');
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['entry_group_id', 'handle'], 'et_group_handle_unique');
            $table->index(['entry_group_id', 'sort_order'], 'et_group_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_types');
    }
};
