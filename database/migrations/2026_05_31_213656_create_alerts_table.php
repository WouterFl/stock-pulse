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
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['absolute_threshold', 'statistical_outlier', 'news_spike']);
            $table->enum('severity', ['info', 'warning', 'critical']);
            $table->string('title'); // bv. "AAPL +4.2% in 1h"
            $table->json('details')->nullable(); // {from, to, change_percent, window_minutes}
            $table->timestamp('triggered_at')->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'triggered_at']);
            $table->index(['company_id', 'type', 'triggered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
