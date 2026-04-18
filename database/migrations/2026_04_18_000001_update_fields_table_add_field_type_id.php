<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            $table->foreignId('field_type_id')
                ->nullable()
                ->after('id')
                ->constrained('field_types')
                ->nullOnDelete();

            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            $table->dropForeign(['field_type_id']);
            $table->dropColumn('field_type_id');
            $table->string('type')->after('id');
        });
    }
};
