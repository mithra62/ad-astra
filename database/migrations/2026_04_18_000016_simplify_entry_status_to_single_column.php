<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One status group per entry group — direct FK replaces M2M
        Schema::table('entry_groups', function (Blueprint $table) {
            $table->foreignId('status_group_id')
                ->nullable()
                ->after('field_layout_id')
                ->constrained('status_groups')
                ->nullOnDelete();
        });

        // One status per entry — direct FK replaces entry_statuses pivot
        Schema::table('entries', function (Blueprint $table) {
            $table->foreignId('status_id')
                ->nullable()
                ->after('entry_type_id')
                ->constrained('statuses')
                ->nullOnDelete();
        });

        // No longer needed
        Schema::dropIfExists('entry_statuses');
        Schema::dropIfExists('status_groupables');
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropColumn('status_id');
        });

        Schema::table('entry_groups', function (Blueprint $table) {
            $table->dropForeign(['status_group_id']);
            $table->dropColumn('status_group_id');
        });

        Schema::create('status_groupables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('status_groups')->cascadeOnDelete();
            $table->unsignedBigInteger('status_groupable_id');
            $table->string('status_groupable_type');
            $table->timestamps();
        });

        Schema::create('entry_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('entries')->cascadeOnDelete();
            $table->foreignId('status_group_id')->constrained('status_groups')->cascadeOnDelete();
            $table->foreignId('status_id')->constrained('statuses')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['entry_id', 'status_group_id']);
        });
    }
};
