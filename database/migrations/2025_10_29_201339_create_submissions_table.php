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
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type')->nullable();
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->float('total_remittance');
            $table->integer('submitted');
            $table->integer('paid_by_check');
            $table->timestamp('payment_date')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('customer_code')->nullable();
            $table->string('approval_code')->nullable();
            $table->string('pdf_url')->nullable();
            $table->integer('checkoff_organization_id')->nullable();
            $table->integer('status');
            $table->integer('commodity_id')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
