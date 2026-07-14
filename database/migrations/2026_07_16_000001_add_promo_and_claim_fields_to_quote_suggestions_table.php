<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_suggestions', function (Blueprint $table) {
            $table->string('promo_code')->nullable()->after('attachment_path');
            $table->string('promo_note')->nullable()->after('promo_code');
            // Claiming requires a logged-in customer (see QuoteRequestController::claimSuggestion)
            // so an org can verify, in person, that whoever shows up with the
            // code is the same account that claimed it.
            $table->foreignId('claimed_by_user_id')->nullable()->after('promo_note')->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable()->after('claimed_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('quote_suggestions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('claimed_by_user_id');
            $table->dropColumn(['promo_code', 'promo_note', 'claimed_at']);
        });
    }
};
