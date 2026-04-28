<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow entry_types.class to be null so that entry types without a custom
 * PHP class can be created via the admin UI.  The EntryTypeRegistry falls back
 * to GeneralEntryType for any record where class is null or the named class
 * does not exist (see EntryTypeRegistry::instantiate()).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('entry_types', function (Blueprint $table) {
            $table->string('class')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('entry_types', function (Blueprint $table) {
            $table->string('class')->nullable(false)->change();
        });
    }
};
