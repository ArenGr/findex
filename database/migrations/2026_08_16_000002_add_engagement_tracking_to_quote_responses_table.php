<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Two things the request status page can't honestly say without them.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_responses', function (Blueprint $table) {
            $table->timestamp('viewed_at')->nullable()->after('status');
            $table->timestamp('valid_until')->nullable()->after('responded_at');

            $table->index(['organization_id', 'status']);
        });

        // An agency that already replied plainly saw the request first.
        DB::table('quote_responses')
            ->whereNotNull('responded_at')
            ->update(['viewed_at' => DB::raw('responded_at')]);
    }

    public function down(): void
    {
        Schema::table('quote_responses', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'status']);
            $table->dropColumn(['viewed_at', 'valid_until']);
        });
    }
};
