<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_statuses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entry_id')
                ->constrained('entries')
                ->cascadeOnDelete();

            // Denormalized for queryability — allows filtering entries by status group
            $table->foreignId('status_group_id')
                ->constrained('status_groups')
                ->cascadeOnDelete();

            $table->foreignId('status_id')
                ->constrained('statuses')
                ->cascadeOnDelete();

            $table->timestamps();

            // One status per group per entry
            $table->unique(['entry_id', 'status_group_id'], 'entry_status_group_unique');

            $table->index(['status_group_id', 'status_id'], 'entry_status_group_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_statuses');
    }
};
