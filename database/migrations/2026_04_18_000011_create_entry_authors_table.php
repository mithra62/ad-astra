<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entry_authors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entry_id')
                ->constrained('entries')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['entry_id', 'user_id'], 'entry_author_unique');
            $table->index(['entry_id', 'sort_order'], 'entry_author_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_authors');
    }
};
