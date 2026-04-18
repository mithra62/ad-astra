<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_groupables', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->constrained('status_groups')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('status_groupable_id');
            $table->string('status_groupable_type');

            $table->timestamps();

            $table->index(['status_groupable_id', 'status_groupable_type'], 'sg_type_index');
            $table->unique([
                'group_id',
                'status_groupable_id',
                'status_groupable_type',
            ], 'sg_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_groupables');
    }
};
