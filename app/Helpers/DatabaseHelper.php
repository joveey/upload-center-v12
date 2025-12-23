<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class DatabaseHelper
{
    /**
     * Get list of databases from SQL Server
     * @param string $connection Connection name (e.g., 'sqlsrv', 'legacy_26', etc)
     * @return array List of database names
     */
    public static function getDatabaseList(string $connection = 'sqlsrv'): array
    {
        try {
            $databases = DB::connection($connection)
                ->select("SELECT name FROM sys.databases WHERE database_id > 4 ORDER BY name");
            
            return collect($databases)
                ->pluck('name')
                ->toArray();
        } catch (\Exception $e) {
            \Log::error("Failed to get database list from {$connection}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Switch connection to use specific database
     * @param string $connection Connection name
     * @param string $database Database name to switch to
     * @return bool Success status
     */
    public static function switchDatabase(string $connection, string $database): bool
    {
        try {
            DB::connection($connection)->statement("USE [{$database}]");
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to switch to database {$database} on {$connection}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get current database name
     * @param string $connection Connection name
     * @return string|null Current database name
     */
    public static function getCurrentDatabase(string $connection = 'sqlsrv'): ?string
    {
        try {
            $result = DB::connection($connection)
                ->selectOne("SELECT DB_NAME() as db_name");
            
            return $result?->db_name ?? null;
        } catch (\Exception $e) {
            \Log::error("Failed to get current database from {$connection}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Test connection to a specific server
     * @param string $host Server IP/hostname
     * @param int $port Port number
     * @param string $username Username
     * @param string $password Password
     * @return bool Connection success
     */
    public static function testServerConnection(string $host, int $port, string $username, string $password): bool
    {
        try {
            $pdo = new \PDO(
                "sqlsrv:Server={$host},{$port}",
                $username,
                $password,
                ['TrustServerCertificate' => true]
            );
            $pdo = null;
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to connect to {$host}:{$port} - " . $e->getMessage());
            return false;
        }
    }
}
