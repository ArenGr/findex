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
        Schema::create('scraper_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scraping_job_id')->constrained()->cascadeOnDelete();
            $table->enum('level', ['info', 'warning', 'error', 'debug'])->default('info');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['scraping_job_id', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scraper_logs');
    }
};
