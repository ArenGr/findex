<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A customer's budget is a range they're willing to spend within (e.g.
 * "$2000-2500"), not a single figure - lets a partner see the ceiling
 * they're quoting against, not just a bare minimum. Replaces the single
 * budget_amd added in 2026_07_15_000005_add_lead_quality_filters; existing
 * values become budget_min_amd (that's what they were already compared
 * against a partner's minimum as).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->decimal('budget_min_amd', 10, 2)->nullable()->after('budget_amd');
            $table->decimal('budget_max_amd', 10, 2)->nullable()->after('budget_min_amd');
        });

        DB::table('quote_requests')->whereNotNull('budget_amd')->update([
            'budget_min_amd' => DB::raw('budget_amd'),
        ]);

        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn('budget_amd');
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->decimal('budget_amd', 10, 2)->nullable()->after('insurance');
        });

        DB::table('quote_requests')->whereNotNull('budget_min_amd')->update([
            'budget_amd' => DB::raw('budget_min_amd'),
        ]);

        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn(['budget_min_amd', 'budget_max_amd']);
        });
    }
};
