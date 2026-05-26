<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('entry_trees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entry_id')
                ->unique()
                ->constrained('entries')
                ->cascadeOnDelete();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('entry_trees')
                ->nullOnDelete();

            $table->string('handle');
            $table->string('uri')->unique();

            $table->unsignedInteger('depth')->default(0);
            $table->unsignedInteger('sort_order')->default(0);

            $table->string('template')->nullable();
            $table->string('redirect_url')->nullable();
            $table->unsignedSmallInteger('redirect_status')->default(302);
            $table->boolean('is_home')->default(false);

            $table->timestamps();

            $table->unique(['parent_id', 'handle']);
            $table->index(['parent_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entry_trees');
    }
};
