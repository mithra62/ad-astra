<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill any rows that somehow have a NULL name before tightening the constraint.
        DB::table('field_layouts')
            ->whereNull('name')
            ->update(['name' => 'Unnamed Layout']);

        Schema::table('field_layouts', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('field_layouts', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
        });
    }
};
