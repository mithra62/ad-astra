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
        Schema::create('category_media', function (Blueprint $table) {
            $table->unsignedBigInteger('cat_id');
            $table->unsignedBigInteger('media_id');

            $table->foreign('cat_id')
                ->references('id')
                ->on('categories')
                ->onDelete('cascade');

            $table->foreign('media_id')
                ->references('id')
                ->on('media')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_media');
    }
};
