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
        Schema::create('setting_values', function (Blueprint $table) {
            $table->id();

            // Which domain this value belongs to (e.g. 'general', 'media')
            $table->string('domain');

            // Which field within that domain
            $table->string('field_handle');

            // NULL = system-wide value; set = per-user override
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();

            // Typed storage columns — only the column matching the field's declared
            // type is written; the rest remain NULL for that row.
            $table->text('value_text')->nullable();
            $table->bigInteger('value_integer')->nullable();
            $table->double('value_float')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->json('value_json')->nullable();

            $table->timestamps();

            // One value per domain + field + user combination
            $table->unique(['domain', 'field_handle', 'user_id'], 'sv_domain_field_user_unique');

            // Fast lookup: all values for a user in a domain
            $table->index(['user_id', 'domain'], 'sv_user_domain_idx');

            // Fast lookup: all system values for a domain
            $table->index(['domain', 'user_id'], 'sv_domain_user_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('setting_values');
    }
};
