<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * First step of unifying account storage - organizations and admins
 * currently keep their own separate email/password columns (see
 * 2026_07_06_000001_add_organization_auth_columns_to_organizations_table
 * and 2026_07_06_000008_create_admins_table). This adds the columns
 * `users` needs to become the single account table for every role; the
 * data itself is copied over in the next migration
 * (2026_07_13_000002_migrate_organizations_and_admins_into_users_table),
 * kept separate so this purely-additive step and that data-carrying step
 * can be reasoned about independently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Numeric rather than a string column deliberately - see
            // App\Enums\UserRole for what each value means (1=admin,
            // 2=organization, 3=customer). Not referenced directly from
            // here so this migration keeps working unchanged if that enum
            // is ever renamed/moved.
            $table->unsignedTinyInteger('role')->default(3)->after('password')->index();
            $table->foreignId('organization_id')->nullable()->after('role')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn('role');
        });
    }
};
