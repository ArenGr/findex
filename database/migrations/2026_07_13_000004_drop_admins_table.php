<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin accounts now live on `users` with role='admin' (see
 * 2026_07_13_000002_migrate_organizations_and_admins_into_users_table).
 * down() only recreates the empty schema - the original rows are not
 * recoverable from here - take a DB backup before running up() in
 * production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('admins');
    }

    public function down(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }
};
