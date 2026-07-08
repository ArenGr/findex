<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * reviews, reports, report_requests, currency_rates and mortgage_offers
     * all cascadeOnDelete() from organizations - an accidental (or
     * malicious) admin delete previously destroyed all of that permanently
     * in one shot. Soft-deleting the organization means Filament's delete
     * action (which just calls $model->delete()) now sets deleted_at
     * instead of issuing a real DELETE, so the foreign key cascades never
     * fire and the org (and everything under it) stays recoverable.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
