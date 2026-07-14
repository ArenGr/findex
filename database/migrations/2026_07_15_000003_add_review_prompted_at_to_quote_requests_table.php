<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks whether a completed trip's post-trip review nudge has already
 * been sent (see PromptTripReviews) - the trip itself isn't tied to a
 * booking made through this platform, so this is the only signal we have
 * that the travel dates have passed and a review is worth asking for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->timestamp('review_prompted_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn('review_prompted_at');
        });
    }
};
