<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('entry_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')
                ->constrained('entries')
                ->cascadeOnDelete();
            $table->string('metric');           // e.g. 'downloads', 'views', 'plays'
            $table->unsignedBigInteger('value')->default(0);
            $table->date('recorded_date');
            $table->timestamps();

            $table->unique(['entry_id', 'metric', 'recorded_date']);
            $table->index(['entry_id', 'metric']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_metrics');
    }
};
