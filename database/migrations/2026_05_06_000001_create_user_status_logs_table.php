<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_status_logs', function (Blueprint $table) {
            $table->id();

            // The user whose status changed.
            // Cascade delete: if the user is hard-deleted, their audit rows go too.
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // The admin who made the change.
            // Null on delete: preserve the log row if the actor is later removed.
            $table->foreignId('changed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Status columns — both null for lock-only changes.
            $table->string('previous_status', 20)->nullable();
            $table->string('new_status', 20)->nullable();

            // Lock columns — both null for status-only changes.
            $table->timestamp('previous_locked_until')->nullable();
            $table->timestamp('new_locked_until')->nullable();

            $table->string('reason', 500)->nullable();
            $table->json('context')->nullable();

            // Append-only — no updated_at.
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_status_logs');
    }
};
