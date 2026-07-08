<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `rating` was only ever bounded to 1-5 in ReviewController's validation
     * - any other write path (a seeder, tinker, a future API) could insert 0
     * or 255. SQLite's schema builder can't add a CHECK constraint to an
     * existing table without a full table rebuild, and this app already
     * runs on MySQL in every real environment (see config/database.php), so
     * this is skipped there rather than adding that complexity for a
     * developer-only sqlite database.
     */
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
