<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Copies existing login credentials off `organizations` (email/password/
 * remember_token - added in 2026_07_06_000001_add_organization_auth_columns_to_organizations_table)
 * and off `admins` entirely, into `users` rows with the matching `role`.
 * Uses the query builder rather than Eloquent throughout: the `User`
 * model's password cast is 'hashed', which re-hashes on every set unless
 * Hash::isHashed() already sees a bcrypt string - going through DB::table
 * sidesteps relying on that behavior (and avoids firing model events)
 * while copying an already-hashed value verbatim.
 *
 * Organizations without an email (the seeded source-only banks in
 * OrganizationSeeder never had login credentials) are skipped - there's
 * nothing to migrate for them.
 *
 * `notifications.notifiable_type/notifiable_id` (see
 * 2026_07_08_000001_create_notifications_table) has existing rows pointing
 * at `App\Models\Admin` for the admin-approval and scraper-alert
 * notifications (see RegisteredOrganizationController::notifyAdminsOfPendingApproval
 * and AdminNotifier) - those are rewritten to point at the new User rows
 * so existing notification-bell history doesn't orphan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->assertNoEmailCollisions();

            foreach (DB::table('organizations')->whereNotNull('email')->get() as $organization) {
                DB::table('users')->insert([
                    'name' => $organization->name,
                    'email' => $organization->email,
                    'password' => $organization->password,
                    'remember_token' => $organization->remember_token,
                    'role' => 2, // App\Enums\UserRole::ORGANIZATION
                    'organization_id' => $organization->id,
                    'created_at' => $organization->created_at,
                    'updated_at' => $organization->updated_at,
                ]);
            }

            $adminIdToUserId = [];

            foreach (DB::table('admins')->get() as $admin) {
                $adminIdToUserId[$admin->id] = DB::table('users')->insertGetId([
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'password' => $admin->password,
                    'remember_token' => $admin->remember_token,
                    'role' => 1, // App\Enums\UserRole::ADMIN
                    'organization_id' => null,
                    'created_at' => $admin->created_at,
                    'updated_at' => $admin->updated_at,
                ]);
            }

            foreach ($adminIdToUserId as $oldAdminId => $newUserId) {
                DB::table('notifications')
                    ->where('notifiable_type', 'App\\Models\\Admin')
                    ->where('notifiable_id', $oldAdminId)
                    ->update([
                        'notifiable_type' => 'App\\Models\\User',
                        'notifiable_id' => $newUserId,
                    ]);
            }
        });
    }

    /**
     * `users.email` is unique - abort loudly rather than silently mangling
     * an account's login email if an organization or admin happens to
     * share an email with an existing customer (or with each other).
     */
    private function assertNoEmailCollisions(): void
    {
        $existingUserEmails = DB::table('users')->pluck('email');
        $organizationEmails = DB::table('organizations')->whereNotNull('email')->pluck('email');
        $adminEmails = DB::table('admins')->pluck('email');

        $collisions = $existingUserEmails->intersect($organizationEmails)
            ->merge($existingUserEmails->intersect($adminEmails))
            ->merge($organizationEmails->intersect($adminEmails))
            ->unique();

        if ($collisions->isNotEmpty()) {
            throw new \RuntimeException(
                'Cannot migrate organizations/admins into users: email(s) already in use - '
                .$collisions->implode(', ').'. Resolve manually (rename one side\'s email) and re-run.'
            );
        }
    }

    /**
     * Best-effort only - deletes the rows this migration created, but
     * can't recover the original admin ids to un-remap `notifications`
     * (that mapping only exists in-memory during up()). Take a DB backup
     * before running up() in production rather than relying on this.
     */
    public function down(): void
    {
        DB::table('users')->whereIn('role', [1, 2])->delete(); // ADMIN, ORGANIZATION
    }
};
