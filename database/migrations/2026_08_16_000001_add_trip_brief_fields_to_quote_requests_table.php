<?php

use App\Enums\QuoteRequestStatus;
use App\Models\QuoteRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widens a quote request from "where and when" into the full trip brief an
 * agency actually needs to price a package: where the traveler is flying
 * out of, how firm the dates are, whether flights belong in the quote, what
 * hotel class and meal plan they want, and which few things matter most.
 *
 * meal_preference subsumes the old all_inclusive boolean - "all inclusive"
 * was only ever one point on a scale that also has breakfast-only, half and
 * full board, and keeping both would mean storing the same fact twice.
 * Existing rows carry their boolean across (true -> all_inclusive, false ->
 * any, i.e. "not stated" rather than "explicitly not all-inclusive", which
 * is all an unchecked box ever meant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->string('departure_location')->nullable()->after('locale');

            // Null means fixed dates. A number is how many days either side
            // the traveler will accept, which is what an agency needs in
            // order to go looking for a cheaper departure - a bare "flexible"
            // flag would leave them guessing how far they may move it.
            $table->unsignedTinyInteger('flexible_days')->nullable()->after('check_out');

            $table->string('flight_preference', 20)->default(QuoteRequest::FLIGHT_FLEXIBLE)->after('children');
            $table->string('hotel_preference', 10)->default(QuoteRequest::HOTEL_ANY)->after('flight_preference');
            $table->string('meal_preference', 20)->default(QuoteRequest::MEAL_ANY)->after('hotel_preference');

            // A short opt-in list (see QuoteRequest::PRIORITIES), capped at
            // MAX_PRIORITIES. JSON rather than a child table for the same
            // reason DESTINATIONS is a const list and not a table: the set is
            // fixed in code, never joined against, and only ever read back
            // whole alongside the request it belongs to.
            $table->json('priorities')->nullable()->after('meal_preference');

            // The currency the traveler stated their budget in. The
            // budget_min_amd/budget_max_amd columns stay the single source of
            // truth for matching (see Organization::tourismPartnersForDestination),
            // so this is display only - it stops a budget entered as "2000
            // USD" being read back as a bare AMD figure.
            $table->string('budget_currency', 3)->default('AMD')->after('budget_max_amd');

            // Only ever holds a decided state (submitted / offers_received /
            // closed). "Expired" is not written here - it is a function of
            // expires_at and would go stale the moment the clock passed it,
            // so QuoteRequest::currentStatus() folds it in at read time.
            $table->string('status', 20)->default(QuoteRequestStatus::SUBMITTED->value)->after('notes');

            // Serves the "my requests" list (owner's rows, newest first) and
            // the expiry sweep the status page and reminders both lean on.
            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        DB::table('quote_requests')->where('all_inclusive', true)->update([
            'meal_preference' => QuoteRequest::MEAL_ALL_INCLUSIVE,
        ]);

        // Every request that already has a reply is past "submitted" - without
        // this, historical rows would all report as still waiting on their
        // first offer.
        DB::table('quote_requests')
            ->whereIn('id', fn ($query) => $query->select('quote_request_id')
                ->from('quote_responses')
                ->whereNotNull('responded_at'))
            ->update(['status' => QuoteRequestStatus::OFFERS_RECEIVED->value]);

        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn('all_inclusive');
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->boolean('all_inclusive')->default(false)->after('children');
        });

        DB::table('quote_requests')
            ->where('meal_preference', QuoteRequest::MEAL_ALL_INCLUSIVE)
            ->update(['all_inclusive' => true]);

        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['status', 'expires_at']);

            $table->dropColumn([
                'departure_location',
                'flexible_days',
                'flight_preference',
                'hotel_preference',
                'meal_preference',
                'priorities',
                'budget_currency',
                'status',
            ]);
        });
    }
};
