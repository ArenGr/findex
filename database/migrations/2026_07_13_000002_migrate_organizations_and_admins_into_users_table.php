<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
            throw new RuntimeException(
                'Cannot migrate organizations/admins into users: email(s) already in use - '
                .$collisions->implode(', ').'. Resolve manually (rename one side\'s email) and re-run.'
            );
        }
    }

    public function down(): void
    {
        DB::table('users')->whereIn('role', [1, 2])->delete(); // ADMIN, ORGANIZATION
    }
};
