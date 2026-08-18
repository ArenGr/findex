<?php

use App\Models\QuoteRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A trip is no longer one country on fixed dates.
 *
 * The approved design lets a traveller name several destinations (or none
 * at all, if they're open to suggestions), say how firm their dates are,
 * and give each child's age. Each of those is a fact an agency prices
 * against, so each gets a column rather than being flattened into notes.
 *
 * destination_country stays, holding the first of destination_countries.
 * Everything that reads a request one-destination-at-a-time - the Telegram
 * brief, the emails, the destination alerts - keeps working untouched, and
 * only the places that genuinely care about the full list ask for it. It
 * becomes nullable because "open to suggestions" is a real answer with no
 * country in it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->json('destination_countries')->nullable()->after('destination_country');
            $table->boolean('open_to_suggestions')->default(false)->after('destination_countries');

            // One age per child, in the order they were entered. JSON for the
            // same reason as priorities: a fixed-length list, read back whole
            // with its request, never joined against.
            $table->json('child_ages')->nullable()->after('children');

            // Replaces flexible_days below. That column could say "give or
            // take 3 days" but had no way to say "anywhere this month",
            // which the design offers alongside it - so the whole answer
            // moves to one column that can hold all three.
            $table->string('date_flexibility', 10)->nullable()->after('check_out');
        });

        Schema::table('quote_requests', function (Blueprint $table) {
            $table->string('destination_country', 2)->nullable()->change();
        });

        // Existing rows have exactly one destination - it becomes a
        // single-entry list, so nothing has to special-case older requests.
        DB::table('quote_requests')
            ->whereNotNull('destination_country')
            ->update([
                'destination_countries' => DB::raw("json_array(destination_country)"),
            ]);

        DB::table('quote_requests')->where('flexible_days', 3)->update(['date_flexibility' => QuoteRequest::DATES_PLUS_3]);
        DB::table('quote_requests')->where('flexible_days', 7)->update(['date_flexibility' => QuoteRequest::DATES_PLUS_7]);

        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn('flexible_days');
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('flexible_days')->nullable()->after('check_out');
        });

        DB::table('quote_requests')->where('date_flexibility', QuoteRequest::DATES_PLUS_3)->update(['flexible_days' => 3]);
        DB::table('quote_requests')->where('date_flexibility', QuoteRequest::DATES_PLUS_7)->update(['flexible_days' => 7]);

        // A request with no destination at all can't survive the column
        // going back to NOT NULL - there is nothing truthful to put in it,
        // so those rows are dropped rather than given a made-up country.
        DB::table('quote_requests')->whereNull('destination_country')->delete();

        Schema::table('quote_requests', function (Blueprint $table) {
            $table->string('destination_country', 2)->nullable(false)->change();
        });

        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn(['destination_countries', 'open_to_suggestions', 'child_ages', 'date_flexibility']);
        });
    }
};
