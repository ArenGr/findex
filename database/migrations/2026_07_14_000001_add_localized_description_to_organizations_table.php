<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Organizations serve customers across all three supported locales (see
 * config('localization.available')), but the profile description was a
 * single free-text column - every visitor saw whatever language the org
 * happened to write it in. Splits it into one column per locale so an org
 * can (optionally) write a description for each language;
 * Organization::getDescriptionAttribute() picks the current visitor's
 * locale, falling back through the others if that one's blank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->text('description_hy')->nullable()->after('description');
            $table->text('description_en')->nullable()->after('description_hy');
            $table->text('description_ru')->nullable()->after('description_en');
        });

        // Existing descriptions were authored in whatever language the org
        // used - 'hy' is the site's default locale
        // (config('localization.default')), the most likely authoring
        // language for organizations profiled so far.
        DB::table('organizations')->whereNotNull('description')->update([
            'description_hy' => DB::raw('description'),
        ]);

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->text('description')->nullable()->after('logo');
        });

        DB::table('organizations')->whereNotNull('description_hy')->update([
            'description' => DB::raw('description_hy'),
        ]);

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['description_hy', 'description_en', 'description_ru']);
        });
    }
};
