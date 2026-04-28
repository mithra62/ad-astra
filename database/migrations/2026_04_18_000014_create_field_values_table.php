<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('field_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('field_id')
                ->constrained('fields')
                ->cascadeOnDelete();

            // Polymorphic owner: Entry, Category, User
            $table->unsignedBigInteger('fieldable_id');
            $table->string('fieldable_type');

            // Typed value columns — each FieldType writes to its declared column
            $table->text('value_text')->nullable();
            $table->bigInteger('value_integer')->nullable();
            $table->double('value_float')->nullable();
            $table->dateTime('value_date')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->json('value_json')->nullable();

            $table->timestamps();

            // One value per field per fieldable entity
            $table->unique(['field_id', 'fieldable_id', 'fieldable_type'], 'fv_field_fieldable_unique');

            $table->index(['fieldable_id', 'fieldable_type'], 'fv_fieldable_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_values');
    }
};
