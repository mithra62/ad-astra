<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('gate_bypass_logs', function (Blueprint $table) {
            $table->id();

            // The super admin whose gate check was bypassed.
            // Null on delete: audit rows must outlive the account.
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('ability');

            // Morph alias (or raw class string for class-level checks like
            // 'create'); id is a string so non-integer keys are tolerated.
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();

            // Request columns — all null for console / queue bypasses.
            $table->string('method', 10)->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('route_name')->nullable();
            $table->string('ip', 45)->nullable();

            // Identical checks within one request are deduped into a single
            // row with an occurrence count.
            $table->unsignedInteger('occurrences')->default(1);

            $table->json('context')->nullable();

            // Append-only — no updated_at.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('ability');
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at'); // prune scan
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_bypass_logs');
    }
};
