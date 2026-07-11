<?php

use App\Models\QuoteResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Backs the secure, no-login partner response page: response_token is
     * the credential embedded in the link we send, status tracks the
     * pending/responded/declined lifecycle explicitly (rather than
     * inferring it from responded_at being null), and the remaining
     * columns are the structured fields the response form collects.
     */
    public function up(): void
    {
        Schema::table('quote_responses', function (Blueprint $table) {
            $table->string('response_token', 64)->nullable()->after('organization_id');
            $table->string('status', 20)->default(QuoteResponse::STATUS_PENDING)->after('response_token');
            $table->string('offered_hotel_name')->nullable()->after('price_currency');
            $table->text('flight_details')->nullable()->after('offered_hotel_name');
            $table->text('inclusions')->nullable()->after('flight_details');
            $table->string('attachment_path')->nullable()->after('reply_text');
        });

        // Backfill: every existing row needs a token (used to be nullable
        // only so the column could be added without a default), and any row
        // that already has a reply counts as already "responded" so it
        // doesn't regress to "pending" under the new explicit status.
        DB::table('quote_responses')->orderBy('id')->get(['id', 'responded_at'])->each(function ($row) {
            DB::table('quote_responses')->where('id', $row->id)->update([
                'response_token' => Str::random(40),
                'status' => $row->responded_at ? QuoteResponse::STATUS_RESPONDED : QuoteResponse::STATUS_PENDING,
            ]);
        });

        Schema::table('quote_responses', function (Blueprint $table) {
            $table->string('response_token', 64)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('quote_responses', function (Blueprint $table) {
            $table->dropColumn([
                'response_token',
                'status',
                'offered_hotel_name',
                'flight_details',
                'inclusions',
                'attachment_path',
            ]);
        });
    }
};
