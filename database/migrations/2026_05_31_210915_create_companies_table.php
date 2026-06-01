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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('ticker');            // bv. AAPL, ASML
            $table->string('exchange');          // bv. NASDAQ, AMS, NYSE
            $table->string('name');
            $table->string('currency', 3)->default('USD'); // bv. USD, EUR
            $table->string('sector')->nullable();
            $table->string('industry')->nullable();
            $table->string('logo_url')->nullable();
            $table->boolean('is_active')->default(true);          // pauzeer scraping zonder te verwijderen
            $table->unsignedInteger('polling_interval_seconds')->default(60); // per-bedrijf override
            $table->decimal('alert_threshold_percent', 6, 2)->nullable();     // override op globale drempel
            $table->boolean('alert_use_statistical')->default(false);         // 2σ-detectie aan/uit
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('ticker');
            $table->index('exchange');
            $table->unique(['ticker', 'exchange']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
