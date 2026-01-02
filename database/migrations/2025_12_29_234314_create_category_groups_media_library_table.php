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
        Schema::create('category_groups_media_library', function (Blueprint $table) {
            //$table->foreignId('group_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('library_id');

            $table->foreign('group_id')
                ->references('id')
                ->on('category_groups')
                ->onDelete('cascade');

            $table->foreign('library_id')
                ->references('id')
                ->on('media_libraries')
                ->onDelete('cascade');

            //$table->foreignId('library_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_groups_media_library');
    }
};
