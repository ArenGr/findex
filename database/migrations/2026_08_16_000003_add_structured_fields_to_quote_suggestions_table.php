<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_suggestions', function (Blueprint $table) {
            $table->unsignedTinyInteger('hotel_stars')->nullable()->after('offered_hotel_name');

            $table->boolean('flight_included')->nullable()->after('hotel_stars');
            $table->string('flight_type', 20)->nullable()->after('flight_included');

            $table->string('meal_plan', 20)->nullable()->after('flight_details');
            $table->boolean('transfer_included')->nullable()->after('meal_plan');
            $table->boolean('insurance_included')->nullable()->after('transfer_included');

            // The traveler picking this option.
            $table->timestamp('selected_at')->nullable()->after('claimed_at');
        });
    }

    public function down(): void
    {
        Schema::table('quote_suggestions', function (Blueprint $table) {
            $table->dropColumn([
                'hotel_stars',
                'flight_included',
                'flight_type',
                'meal_plan',
                'transfer_included',
                'insurance_included',
                'selected_at',
            ]);
        });
    }
};
