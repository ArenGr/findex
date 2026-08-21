<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A few sources only answer to a specific request header, and it is not
     * one that can be sent to every site.
     *
     * Armeconombank's branch endpoint checks for
     * "X-Requested-With: XMLHttpRequest" and returns a 404 page without it.
     * Sending that header everywhere is not an option: many frameworks
     * switch a page to a JSON response when they see it, which would break
     * the scrapers that read markup.
     */
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
