<?php

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
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('guest_name')->nullable()->after('user_id');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // MySQL treats NULL as distinct in a unique index, so the existing
        // (organization_id, user_id) unique constraint already lets multiple
        // guest reviews (user_id null) through untouched - only authenticated
        // users are still limited to one review per organization.
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_guest_name_or_user_id CHECK (user_id IS NOT NULL OR guest_name IS NOT NULL)');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_guest_name_or_user_id CHECK (user_id IS NOT NULL OR guest_name IS NOT NULL)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE reviews DROP CHECK reviews_guest_name_or_user_id');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE reviews DROP CONSTRAINT reviews_guest_name_or_user_id');
        }

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('guest_name');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
