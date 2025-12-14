<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('remittances', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type');
            $table->float('total');
            $table->float('num_bushels_purchased')->nullable();
            $table->foreignId('us_state_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('commodity_id')->constrained()->cascadeOnDelete();
            $table->integer('first_purchased_submission_id')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();
        });

        Schema::create('remittance_meta', function (Blueprint $table) {
            $table->foreignId('remittance_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('value');
            $table->timestamps();
            $table->primary(['remittance_id', 'key'],
                'remittance_meta_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('remittances');
        Schema::dropIfExists('remittance_meta');
    }
};
