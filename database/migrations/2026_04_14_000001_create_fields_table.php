<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_type_id')
                ->nullable()
                ->constrained('field_types')
                ->nullOnDelete();
            $table->string('name');
            $table->string('handle')->index();
            $table->string('label')->nullable();
            $table->text('instructions')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('hidden')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fields');
    }
};
