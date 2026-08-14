<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One JSON column rather than a branch_hours table: opening hours are only
     * ever read as a whole ("is this open now", "what does it say for Tuesday")
     * and never queried across branches, so seven rows per branch would buy
     * joins we would never use.
     *
     * Shape is {"mon": ["09:00", "18:00"], ..., "sun": null}, where null means
     * closed that day and a missing column means we simply do not know - which
     * is different, and the UI says nothing rather than guessing.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->json('opening_hours')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('opening_hours');
        });
    }
};
