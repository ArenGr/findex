<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_between_1_and_5 CHECK (rating BETWEEN 1 AND 5)');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_between_1_and_5 CHECK (rating BETWEEN 1 AND 5)');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE reviews DROP CHECK reviews_rating_between_1_and_5');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE reviews DROP CONSTRAINT reviews_rating_between_1_and_5');
        }
    }
};
