<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
