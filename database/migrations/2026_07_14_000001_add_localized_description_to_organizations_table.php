<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->text('description_hy')->nullable()->after('description');
            $table->text('description_en')->nullable()->after('description_hy');
            $table->text('description_ru')->nullable()->after('description_en');
        });

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
