<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Login credentials now live on `users` (see
 * 2026_07_13_000002_migrate_organizations_and_admins_into_users_table) -
 * `organizations` goes back to being a pure business-profile entity.
 * down() re-adds the columns empty; the original values are not
 * recoverable from here (they live on `users` now) - take a DB backup
 * before running up() in production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // The unique index on email must go first - SQLite (used in
            // tests) errors dropping a column that's still indexed, unlike
            // MySQL which drops dependent indexes automatically.
            $table->dropUnique(['email']);
            $table->dropColumn(['email', 'password', 'remember_token']);
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->after('name');
            $table->string('password')->nullable()->after('email');
            $table->rememberToken();
        });
    }
};
