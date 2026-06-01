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
        Schema::create('news_articles', function (Blueprint $table) {
            $table->id();
            $table->string('source');                  // bv. marketaux, yahoo_rss, reuters_rss
            $table->string('external_id')->nullable(); // voor deduplicatie per bron
            $table->string('url', 1024);
            $table->char('url_hash', 64)->unique();    // sha256 van url voor snelle lookups
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_url', 1024)->nullable();
            $table->timestamp('published_at')->index();
            $table->string('language', 5)->nullable();
            $table->decimal('sentiment', 4, 3)->nullable(); // -1..1, voor later
            $table->timestamps();

            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_articles');
    }
};
