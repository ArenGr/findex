<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The owner's passport/ID/PSC number is no longer stored.
    public function up(): void
    {
        Schema::table('auto_insurance_requests', function (Blueprint $table) {
            $table->dropColumn('owner_id_number');
        });
    }

    public function down(): void
    {
        Schema::table('auto_insurance_requests', function (Blueprint $table) {
            $table->string('owner_id_number')->nullable()->after('owner_type');
        });
    }
};
