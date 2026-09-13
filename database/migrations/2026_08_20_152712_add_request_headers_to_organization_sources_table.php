<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_sources', function (Blueprint $table) {
            $table->json('request_headers')->nullable()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('organization_sources', function (Blueprint $table) {
            $table->dropColumn('request_headers');
        });
    }
};
