<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fieldables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')
                ->constrained('fields')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('fieldable_id');
            $table->string('fieldable_type');
            $table->timestamps();
            $table->index(['fieldable_id', 'fieldable_type'], 'f_type_index');
            $table->unique([
                'field_id',
                'fieldable_id',
                'fieldable_type',
            ], 'f_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fieldables');
    }
};
