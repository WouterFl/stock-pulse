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
        // Append-only tijdsreeks: geen updated_at, alleen created_at + fetched_at.
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 16, 6);
            $table->decimal('open', 16, 6)->nullable();
            $table->decimal('high', 16, 6)->nullable();
            $table->decimal('low', 16, 6)->nullable();
            $table->decimal('previous_close', 16, 6)->nullable();
            $table->unsignedBigInteger('volume')->nullable();
            $table->decimal('change_percent', 10, 4)->nullable(); // t.o.v. previous_close
            $table->string('source');                              // welke provider de data leverde
            $table->timestamp('fetched_at');
            $table->timestamp('created_at')->nullable();

            // Composite index voor grafiek-queries (per bedrijf, op tijd).
            $table->index(['company_id', 'fetched_at']);
            // Losse index op fetched_at voor het opschonen van oude data (SP-10).
            $table->index('fetched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
