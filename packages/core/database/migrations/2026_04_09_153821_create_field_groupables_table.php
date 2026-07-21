<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('field_groupables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')
                ->constrained('field_groups')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('field_groupable_id');
            $table->string('field_groupable_type');
            $table->timestamps();
            $table->index(['field_groupable_id', 'field_groupable_type'], 'fg_type_index');
            $table->unique([
                'group_id',
                'field_groupable_id',
                'field_groupable_type',
            ], 'fg_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_groupables');
    }
};
