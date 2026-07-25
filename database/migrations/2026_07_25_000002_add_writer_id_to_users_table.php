<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors 2026_07_13_000001_add_role_and_organization_id_to_users_table's
 * organization_id half - role already exists on users (UserRole::WRITER is
 * just a new case of the same column), this just adds the FK link to the
 * new writers profile table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('writer_id')->nullable()->after('organization_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('writer_id');
        });
    }
};
