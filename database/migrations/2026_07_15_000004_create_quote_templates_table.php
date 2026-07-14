<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Null = offered regardless of destination - a generic
            // template, not tied to one country.
            $table->string('destination_country', 2)->nullable();
            $table->decimal('price_amount', 10, 2)->nullable();
            $table->string('price_currency', 3)->nullable();
            $table->string('offered_hotel_name')->nullable();
            $table->text('flight_details')->nullable();
            $table->text('inclusions')->nullable();
            $table->text('reply_text')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'destination_country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_templates');
    }
};
