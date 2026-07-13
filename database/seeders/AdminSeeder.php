<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // role isn't fillable (see User::canAccessPanel's docblock), so
        // firstOrCreate's mass-assigned $attributes wouldn't persist it -
        // look up/create then forceFill instead.
        $admin = User::firstOrNew(['email' => 'admin@findex.test']);
        $admin->forceFill([
            'name' => 'Admin',
            'password' => Hash::make('password'),
            'role' => UserRole::ADMIN,
        ])->save();

        $this->command->info('Admin seeded successfully! Email: admin@findex.test / Password: password');
    }
}
