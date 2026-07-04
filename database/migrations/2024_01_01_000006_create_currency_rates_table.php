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
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->decimal('buy_rate', 10, 4);
            $table->decimal('sell_rate', 10, 4);
            $table->string('source_url')->nullable();
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'currency_id']);
            $table->index(['organization_id', 'scraped_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
