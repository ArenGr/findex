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
        Schema::create('organization_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('source_type'); // 'currency_rates', 'deposits', 'loans', 'mortgages', etc.
            $table->string('url'); // Relative or absolute URL
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_scraped_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'source_type']);
            $table->index('source_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_sources');
    }
};
