<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tourism_destinations', function (Blueprint $table) {
            $table->boolean('is_paused')->default(false)->after('country_code');
            $table->date('paused_until')->nullable()->after('is_paused');
        });
    }

    public function down(): void
    {
        Schema::table('tourism_destinations', function (Blueprint $table) {
            $table->dropColumn(['is_paused', 'paused_until']);
        });
    }
};
