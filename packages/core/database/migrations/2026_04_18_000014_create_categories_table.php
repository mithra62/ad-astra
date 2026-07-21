<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
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
            $table->string('handle');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['group_id', 'handle'], 'cat_group_handle_unique');
            $table->index(['group_id', 'parent_id', 'sort_order'], 'cat_group_parent_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
