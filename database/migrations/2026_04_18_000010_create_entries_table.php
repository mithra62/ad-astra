<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entry_group_id')
                ->constrained('entry_groups')
                ->cascadeOnDelete();

            $table->foreignId('entry_type_id')
                ->constrained('entry_types')
                ->restrictOnDelete();

            $table->foreignId('created_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('title');
            $table->string('handle');
            $table->string('status')->nullable()->index();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->unique(['entry_group_id', 'handle'], 'entry_group_handle_unique');

            $table->index(['entry_group_id', 'entry_type_id'], 'entry_group_type_idx');
            $table->index(['entry_group_id', 'published_at'], 'entry_group_published_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
