<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPERADMIN_EMAIL', 'superadmin@example.com');
        $password = env('SUPERADMIN_PASSWORD', 'SuperAdmin123!');

        $division = Division::firstOrCreate(
            ['name' => 'Super Admin'],
            ['is_super_user' => true]
        );

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'division_id' => $division->id,
                'email_verified_at' => now(),
            ]
        );

        $role = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => config('auth.defaults.guard', 'web'),
        ]);

        if (! $user->hasRole($role->name)) {
            $user->assignRole($role);
        }

        $this->command->info("Super admin seeded:");
        $this->command->info("Email   : {$email}");
        $this->command->info("Password: {$password}");
        $this->command->warn('Ubah password ini setelah login pertama.');
    }
}
