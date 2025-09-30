<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating test divisions and users...');

        // Create divisions
        $marketing = Division::firstOrCreate(['name' => 'Marketing']);
        $sales = Division::firstOrCreate(['name' => 'Sales']);
        $it = Division::firstOrCreate(['name' => 'IT']);
        $hr = Division::firstOrCreate(['name' => 'HR']);

        $this->command->info('✓ Divisions created');

        // Create test users
        $testUsers = [
            [
                'name' => 'Admin Marketing',
                'email' => 'marketing@test.com',
                'password' => 'password',
                'division_id' => $marketing->id,
            ],
            [
                'name' => 'Admin Sales',
                'email' => 'sales@test.com',
                'password' => 'password',
                'division_id' => $sales->id,
            ],
            [
                'name' => 'Admin IT',
                'email' => 'it@test.com',
                'password' => 'password',
                'division_id' => $it->id,
            ],
            [
                'name' => 'Admin HR',
                'email' => 'hr@test.com',
                'password' => 'password',
                'division_id' => $hr->id,
            ],
        ];

        foreach ($testUsers as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password']),
                    'division_id' => $userData['division_id'],
                ]
            );

            // Assign role
            if (!$user->hasRole('division-user')) {
                $user->assignRole('division-user');
            }

            $this->command->info("✓ Created: {$userData['name']} ({$userData['email']})");
        }

        $this->command->newLine();
        $this->command->table(
            ['Name', 'Email', 'Password', 'Division'],
            [
                ['Admin Marketing', 'marketing@test.com', 'password', 'Marketing'],
                ['Admin Sales', 'sales@test.com', 'password', 'Sales'],
                ['Admin IT', 'it@test.com', 'password', 'IT'],
                ['Admin HR', 'hr@test.com', 'password', 'HR'],
            ]
        );

        $this->command->info('✅ Test users created successfully!');
    }
}