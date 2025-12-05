<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SuperUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $division = Division::firstOrCreate(
            ['name' => 'SuperUser'],
            ['is_super_user' => true]
        );

        $user = User::firstOrCreate(
            ['email' => 'jovisywl@gmail.com'],
            [
                'name' => 'Super User',
                'password' => Hash::make('password'),
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

        $this->command->info('SuperUser seeded:');
        $this->command->info("Email   : {$user->email}");
        $this->command->info('Password: password');
    }
}
