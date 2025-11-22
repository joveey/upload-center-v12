<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create SuperUser division if not exists
        $superDivision = Division::firstOrCreate(
            ['name' => 'SuperUser'],
            ['is_super_user' => true]
        );

        $this->command->info("SuperUser division created/found: {$superDivision->name}");

        // Create SuperUser account
        $superUser = User::firstOrCreate(
            ['email' => 'admin@company.com'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('Admin123!@#'),
                'division_id' => $superDivision->id,
                'email_verified_at' => now(),
            ]
        );

        // Assign super-admin role
        if (!$superUser->hasRole('super-admin')) {
            $superUser->assignRole('super-admin');
            $this->command->info("Role 'super-admin' assigned to {$superUser->email}");
        }

        $this->command->info('');
        $this->command->info('===========================================');
        $this->command->info('SuperUser Account Created Successfully!');
        $this->command->info('===========================================');
        $this->command->info("Email: {$superUser->email}");
        $this->command->info('Password: Admin123!@#');
        $this->command->info('');
        $this->command->warn('⚠️  IMPORTANT: Change this password immediately after first login!');
        $this->command->info('===========================================');
    }
}
