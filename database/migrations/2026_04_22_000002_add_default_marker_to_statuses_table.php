<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enforce at most one default status per group at the database level.
 *
 * MySQL does not support partial indexes (WHERE is_default = 1), so we use a
 * virtual generated column that echoes status_group_id when is_default = 1
 * and is NULL otherwise. MySQL's unique index treats each NULL as distinct, so
 * multiple non-default rows are allowed; only one row per group can hold an
 * actual status_group_id value, enforcing exactly-one-default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statuses', function (Blueprint $table) {
            $table->unsignedBigInteger('_default_marker')
                ->nullable()
                ->virtualAs('IF(`is_default` = 1, `status_group_id`, NULL)')
                ->after('is_default');

            $table->unique('_default_marker', 'statuses_one_default_per_group');
        });
    }

    public function down(): void
    {
        Schema::table('statuses', function (Blueprint $table) {
            $table->dropUnique('statuses_one_default_per_group');
            $table->dropColumn('_default_marker');
        });
    }
};
