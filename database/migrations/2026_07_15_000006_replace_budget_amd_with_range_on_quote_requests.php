<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A customer's budget is a range they're willing to spend within (e.g.
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
