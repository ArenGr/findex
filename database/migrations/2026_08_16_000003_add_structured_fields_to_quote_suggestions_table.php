<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turns an offer from a price with some prose attached into something two
 * agencies can actually be compared on.
 *
 * flight_details and inclusions stay - they're where an agency says the
 * things a fixed field can't ("outbound 06:15 via Doha, 40kg baggage").
 * What changes is that the facts a traveler compares on - hotel class,
 * meal plan, whether a transfer and insurance are in the price - come out
 * of that prose and into columns, because a comparison table cannot line
 * up two free-text paragraphs and a traveler shouldn't have to read both
 * to find out which one includes the airport transfer.
 *
 * Everything is nullable: these columns land on offers that were submitted
 * before they existed, and backfilling them would mean guessing at an
 * agency's terms from its prose. The offer pages show "not stated" for a
 * null rather than inventing a "no".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_suggestions', function (Blueprint $table) {
            $table->unsignedTinyInteger('hotel_stars')->nullable()->after('offered_hotel_name');

            // Distinct from the request's flight_preference: that's what the
            // traveler asked for, this is what the agency is actually
            // quoting. Null means the agency didn't say.
            $table->boolean('flight_included')->nullable()->after('hotel_stars');
            $table->string('flight_type', 20)->nullable()->after('flight_included');

            $table->string('meal_plan', 20)->nullable()->after('flight_details');
            $table->boolean('transfer_included')->nullable()->after('meal_plan');
            $table->boolean('insurance_included')->nullable()->after('transfer_included');

            // The traveler picking this option. Not a status column: an
            // offer is otherwise described entirely by its response's
            // status and its validity date, and a second status that has to
            // be kept in step with those is a way for them to disagree.
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
