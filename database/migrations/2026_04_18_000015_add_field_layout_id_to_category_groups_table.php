<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_groups', function (Blueprint $table) {
            $table->foreignId('field_layout_id')
                ->nullable()
                ->after('id')
                ->constrained('field_layouts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('category_groups', function (Blueprint $table) {
            $table->dropForeign(['field_layout_id']);
            $table->dropColumn('field_layout_id');
        });
    }
};
