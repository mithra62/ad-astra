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
        Schema::create('media_libraries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('handle')->index();

            $table->string('adapter', 50)->default('local');
            $table->json('adapter_settings')->nullable();
            // $table->string('server_path', 255)->default('');
            // $table->string('url', 100);
            $table->json('allowed_types')->nullable();
            $table->unsignedInteger('max_size')->default(10);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->unique(['name', 'handle']);
        });

        Schema::table('media', function (Blueprint $table) {
            $table->foreignId('library_id')->nullable()->after('collection_name')
                ->constrained('media_libraries')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_libraries');
    }
};
