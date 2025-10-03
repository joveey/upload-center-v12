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
        $this->command->info('Creating SuperUser division and account...');

        // Create SuperUser division if not exists
        $superDivision = Division::firstOrCreate(
            ['name' => 'SuperUser'],
            [
                'description' => 'Super Administrator Division',
                'is_super_user' => true,
            ]
        );

        $this->command->info('✓ SuperUser division created/found');

        // Create SuperUser account if not exists
        $superUser = User::firstOrCreate(
            ['email' => 'superuser@test.com'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('password'),
                'division_id' => $superDivision->id,
                'email_verified_at' => now(),
            ]
        );

        // Assign super-admin role
        if (!$superUser->hasRole('super-admin')) {
            $superUser->assignRole('super-admin');
        }

        $this->command->info('✓ SuperUser account created/updated');

        // Display credentials
        $this->command->newLine();
        $this->command->table(
            ['Name', 'Email', 'Password', 'Division'],
            [
                [
                    $superUser->name,
                    $superUser->email,
                    'password',
                    $superDivision->name,
                ],
            ]
        );

        $this->command->info('✅ SuperUser created successfully!');
        $this->command->warn('⚠️  SuperUser can only be created via seeding, not through registration.');
    }
}
