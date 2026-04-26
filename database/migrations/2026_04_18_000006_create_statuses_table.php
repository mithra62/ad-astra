<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('status_group_id')
                ->constrained('status_groups')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('handle');
            $table->string('color', 7)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_public')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['status_group_id', 'handle'], 'status_group_handle_unique');
            $table->index(['status_group_id', 'sort_order'], 'status_group_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
