<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('entry_authors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('display_name')->nullable();

            $table->enum('status', ['active', 'pending', 'disabled'])->default('pending');

            $table->timestamps();

            $table->index('status', 'entry_authors_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_authors');
    }
};
