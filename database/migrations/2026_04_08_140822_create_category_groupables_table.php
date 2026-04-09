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
        Schema::create('category_groupables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')
                ->constrained('category_groups')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('category_groupable_id');
            $table->string('category_groupable_type');
            $table->timestamps();
            $table->index(['category_groupable_id', 'category_groupable_type'], 'cg_type_index');
            $table->unique([
                'group_id',
                'category_groupable_id',
                'category_groupable_type',
            ], 'cg_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_groupables');
    }
};
