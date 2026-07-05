<?php

use App\Enums\CurrencyCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('code');
        });

        // Backfill from App\Enums\CurrencyCode, the app's single source of
        // truth for which currencies we track and in what order.
        foreach (CurrencyCode::codes() as $index => $code) {
            DB::table('currencies')->where('code', $code)->update(['sort_order' => $index + 1]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
