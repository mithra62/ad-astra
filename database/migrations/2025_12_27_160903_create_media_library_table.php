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
        Schema::create('media_library', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->index();

            $table->string('adapter', 50)->default('local');
            $table->json('adapter_settings')->nullable();
            $table->string('server_path', 255)->default('');
            $table->string('url', 100);
            $table->string('allowed_types', 100)->default('img');
            $table->string('max_size', 16)->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->unique(['name', 'slug']);
        });

        Schema::table('media', function (Blueprint $table) {
            $table->foreignId('library_id')->nullable()->after('collection_name')
                ->constrained('media_library')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_library');
    }
};
