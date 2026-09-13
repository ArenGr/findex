<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->decimal('min_lead_budget_amd', 10, 2)->nullable()->after('telegram_connect_token');
            $table->unsignedTinyInteger('min_lead_party_size')->nullable()->after('min_lead_budget_amd');
        });

        Schema::table('quote_requests', function (Blueprint $table) {
            $table->decimal('budget_amd', 10, 2)->nullable()->after('insurance');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['min_lead_budget_amd', 'min_lead_party_size']);
        });

        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn('budget_amd');
        });
    }
};
