<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('field_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('field_id')
                ->constrained('fields')
                ->cascadeOnDelete();

            // Polymorphic owner: Entry, Category, User
            $table->unsignedBigInteger('fieldable_id');
            $table->string('fieldable_type');

            // Typed value columns — each FieldType writes to its declared column
            $table->text('value_text')->nullable();
            $table->bigInteger('value_integer')->nullable();
            $table->double('value_float')->nullable();
            $table->dateTime('value_date')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->json('value_json')->nullable();

            $table->timestamps();

            // One value per field per fieldable entity
            $table->unique(['field_id', 'fieldable_id', 'fieldable_type'], 'fv_field_fieldable_unique');

            $table->index(['fieldable_id', 'fieldable_type'], 'fv_fieldable_idx');

            // Value-predicate support for whereField(): the unique index above
            // leads (field_id, fieldable_id, ...) so it serves point lookups but
            // not range/equality scans on the typed value columns. These make
            // e.g. whereField('price', '>', 100) index-driven.
            $table->index(['field_id', 'value_integer'], 'fv_field_int_idx');
            $table->index(['field_id', 'value_float'], 'fv_field_float_idx');
            $table->index(['field_id', 'value_date'], 'fv_field_date_idx');
            $table->index(['field_id', 'value_boolean'], 'fv_field_bool_idx');
        });

        // value_text is TEXT, which MySQL/MariaDB can only index with an explicit
        // key prefix length — syntax the schema builder can't express portably.
        // SQLite (test suite) and PostgreSQL index the full column instead.
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement(
                'CREATE INDEX fv_field_text_idx ON '
                . DB::getTablePrefix() . 'field_values (field_id, value_text(191))'
            );
        } else {
            Schema::table('field_values', function (Blueprint $table) {
                $table->index(['field_id', 'value_text'], 'fv_field_text_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('field_values');
    }
};
