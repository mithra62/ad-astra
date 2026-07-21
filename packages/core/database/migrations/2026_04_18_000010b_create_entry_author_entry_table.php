<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('entry_author_entry', function (Blueprint $table) {
            $table->foreignId('entry_id')
                ->constrained('entries')
                ->cascadeOnDelete();

            $table->foreignId('entry_author_id')
                ->constrained('entry_authors')
                ->cascadeOnDelete();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->primary(['entry_id', 'entry_author_id'], 'entry_author_entry_primary');
            $table->index(['entry_id', 'sort_order'], 'entry_author_entry_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_author_entry');
    }
};
