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
        Schema::create('mortgage_offer_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mortgage_offer_id')->constrained('mortgage_offers')->cascadeOnDelete();
            $table->decimal('interest_rate_min', 5, 2);
            $table->decimal('interest_rate_max', 5, 2);
            $table->timestamp('scraped_at');
            $table->timestamps();

            $table->index(['mortgage_offer_id', 'scraped_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mortgage_offer_history');
    }
};
