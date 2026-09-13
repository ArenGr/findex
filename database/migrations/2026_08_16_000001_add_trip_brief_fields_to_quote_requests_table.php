<?php

use App\Enums\QuoteRequestStatus;
use App\Models\QuoteRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->string('departure_location')->nullable()->after('locale');

            // Null means fixed dates.
            $table->unsignedTinyInteger('flexible_days')->nullable()->after('check_out');

            $table->string('flight_preference', 20)->default(QuoteRequest::FLIGHT_FLEXIBLE)->after('children');
            $table->string('hotel_preference', 10)->default(QuoteRequest::HOTEL_ANY)->after('flight_preference');
            $table->string('meal_preference', 20)->default(QuoteRequest::MEAL_ANY)->after('hotel_preference');

            // A short opt-in list (see QuoteRequest::PRIORITIES), capped at MAX_PRIORITIES.
            $table->json('priorities')->nullable()->after('meal_preference');

            // The currency the traveler stated their budget in.
            $table->string('budget_currency', 3)->default('AMD')->after('budget_max_amd');

            // Only ever holds a decided state (submitted / offers_received / closed).
            $table->string('status', 20)->default(QuoteRequestStatus::SUBMITTED->value)->after('notes');

            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        DB::table('quote_requests')->where('all_inclusive', true)->update([
            'meal_preference' => QuoteRequest::MEAL_ALL_INCLUSIVE,
        ]);

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
