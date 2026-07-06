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
        Schema::create('mortgage_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('currency'); // 'AMD', 'USD', 'EUR' - mortgages are quoted in AMD unlike exchange rates, so this isn't a Currency FK
            $table->string('rate_type'); // 'fixed', 'floating_1y', 'floating_3y', 'floating_5y', ...
            $table->decimal('interest_rate_min', 5, 2);
            $table->decimal('interest_rate_max', 5, 2);
            $table->unsignedSmallInteger('term_min_months')->nullable();
            $table->unsignedSmallInteger('term_max_months')->nullable();
            $table->decimal('min_down_payment_percent', 5, 2)->nullable();
            $table->decimal('min_amount', 14, 2)->nullable();
            $table->decimal('max_amount', 14, 2)->nullable();
            $table->string('source_url')->nullable();
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'currency', 'rate_type']);
            $table->index(['organization_id', 'scraped_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mortgage_offers');
    }
};
