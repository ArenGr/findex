<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Email verification enforcement is new - grandfathering every
     * pre-existing account avoids retroactively cutting off orgs already
     * receiving live leads, or blocking customers who've used the site for
     * months, the moment this ships. Only accounts created from here on
     * are actually required to verify (see User::sendEmailVerificationNotification()).
     */
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Not reversible - there's no way to tell which rows were
        // genuinely verified afterward from which were only grandfathered
        // here.
    }
};
