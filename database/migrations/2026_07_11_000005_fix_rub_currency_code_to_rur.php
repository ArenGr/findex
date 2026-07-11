<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The currencies row for the Russian Ruble was originally seeded with code
 * 'RUB' - CurrencyCode::codes() and OrganizationSeeder have always used the
 * correct 'RUR' (the code Armenian banks actually publish rates under), but
 * that only affects newly-seeded rows, not this one already in the table.
 * Existing CurrencyRate rows reference it by currency_id, so this is a safe
 * in-place rename with nothing else to touch.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('currencies')->where('code', 'RUB')->update(['code' => 'RUR']);
    }

    public function down(): void
    {
        DB::table('currencies')->where('code', 'RUR')->update(['code' => 'RUB']);
    }
};
