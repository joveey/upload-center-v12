<?php

namespace App\Console\Commands;

use App\Helpers\DatabaseHelper;
use Illuminate\Console\Command;

class TestDatabaseConnection extends Command
{
    protected $signature = 'db:test-connection {--connection=sqlsrv} {--server=} {--port=1433} {--username=sa} {--password=}';
    protected $description = 'Test SQL Server connection and list available databases';

    public function handle()
    {
        $connection = $this->option('connection');
        $server = $this->option('server') ?? config('database.connections.' . $connection . '.host');
        $port = $this->option('port');
        $username = $this->option('username');
        $password = $this->option('password') ?? config('database.connections.' . $connection . '.password');

        $this->info("Testing connection to {$server}:{$port}...");

        // Test connection
        if (!DatabaseHelper::testServerConnection($server, (int)$port, $username, $password)) {
            $this->error("Failed to connect to {$server}:{$port}");
            return 1;
        }

        $this->info("✓ Connection successful!");

        // Get current database
        $currentDb = DatabaseHelper::getCurrentDatabase($connection);
        $this->info("Current database: {$currentDb}");

        // List all databases
        $this->info("\nFetching available databases...");
        $databases = DatabaseHelper::getDatabaseList($connection);

        if (empty($databases)) {
            $this->warn("No databases found or access denied.");
            return 0;
        }

        $this->info("Available databases (" . count($databases) . "):");
        $this->table(['Database Name'], array_map(fn($db) => [$db], $databases));

        return 0;
    }
}
