<?php

namespace App\Console\Commands;

use App\Models\Division;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateTestUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:create-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create test users for different divisions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating test divisions and users...');

        // Create divisions
        $divisions = [
            'Marketing' => Division::firstOrCreate(['name' => 'Marketing']),
            'Sales' => Division::firstOrCreate(['name' => 'Sales']),
            'IT' => Division::firstOrCreate(['name' => 'IT']),
            'HR' => Division::firstOrCreate(['name' => 'HR']),
        ];

        $this->info('Divisions created/found.');

        // Create test users
        $testUsers = [
            [
                'name' => 'Admin Marketing',
                'email' => 'marketing@test.com',
                'password' => 'password',
                'division' => 'Marketing',
            ],
            [
                'name' => 'Admin Sales',
                'email' => 'sales@test.com',
                'password' => 'password',
                'division' => 'Sales',
            ],
            [
                'name' => 'Admin IT',
                'email' => 'it@test.com',
                'password' => 'password',
                'division' => 'IT',
            ],
            [
                'name' => 'Admin HR',
                'email' => 'hr@test.com',
                'password' => 'password',
                'division' => 'HR',
            ],
        ];

        $this->newLine();
        $this->info('Creating users:');
        $this->newLine();

        foreach ($testUsers as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password']),
                    'division_id' => $divisions[$userData['division']]->id,
                ]
            );

            // Assign role
            if (!$user->hasRole('division-user')) {
                $user->assignRole('division-user');
            }

            $this->line("✓ {$userData['name']} ({$userData['email']}) - Division: {$userData['division']}");
        }

        $this->newLine();
        $this->info('Test users created successfully!');
        $this->newLine();
        $this->table(
            ['Email', 'Password', 'Division'],
            collect($testUsers)->map(fn($u) => [$u['email'], $u['password'], $u['division']])->toArray()
        );

        $this->newLine();
        $this->info('You can now login with any of these credentials to test division isolation.');

        return Command::SUCCESS;
    }
}