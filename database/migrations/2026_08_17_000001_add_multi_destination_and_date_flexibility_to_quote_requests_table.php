<?php

use App\Models\QuoteRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A trip is no longer one country on fixed dates.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->json('destination_countries')->nullable()->after('destination_country');
            $table->boolean('open_to_suggestions')->default(false)->after('destination_countries');

            // One age per child, in the order they were entered.
            $table->json('child_ages')->nullable()->after('children');

            // Replaces flexible_days below.
            $table->string('date_flexibility', 10)->nullable()->after('check_out');
        });

        Schema::table('quote_requests', function (Blueprint $table) {
            $table->string('destination_country', 2)->nullable()->change();
        });

        DB::table('quote_requests')
            ->whereNotNull('destination_country')
            ->update([
                'destination_countries' => DB::raw('json_array(destination_country)'),
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

        DB::table('quote_requests')->whereNull('destination_country')->delete();

        Schema::table('quote_requests', function (Blueprint $table) {
            $table->string('destination_country', 2)->nullable(false)->change();
        });

        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn(['destination_countries', 'open_to_suggestions', 'child_ages', 'date_flexibility']);
        });
    }
};
