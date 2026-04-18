<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropColumn('status_id');

            $table->string('status')->nullable()->after('entry_type_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');

            $table->foreignId('status_id')
                ->nullable()
                ->after('entry_type_id')
                ->constrained('statuses')
                ->nullOnDelete();
        });
    }
};
