<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->constrained('category_groups')
                ->cascadeOnDelete();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            // Slug uniqueness per group (lets you reuse "news" across groups)
            $table->unique(['group_id', 'slug'], 'cat_group_slug_unique');

            $table->index(['group_id', 'parent_id', 'sort_order'], 'cat_group_parent_sort_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
