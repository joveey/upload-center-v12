<?php

namespace App\Http\Controllers;

use App\Helpers\DatabaseHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatabaseServerController extends Controller
{
    /**
     * Get list of configured servers
     */
    public function servers(): JsonResponse
    {
        $servers = explode(',', env('DATABASES_SERVERS', '26,32'));
        $servers = array_map('trim', $servers);
        
        return response()->json([
            'servers' => array_map(fn($s) => ['id' => $s, 'name' => "Server {$s}"], $servers),
        ]);
    }

    /**
     * Get databases from a specific server
     */
    public function databases(Request $request): JsonResponse
    {
        $server = $request->route('server') ?? $request->query('server');
        
        if (!$server) {
            return response()->json(['error' => 'Server parameter required'], 422);
        }

        $connection = "databases_server_{$server}";

        // Build a temporary connection for this server using the legacy/base config.
        if (!config("database.connections.{$connection}")) {
            $baseConfig = config('database.connections.sqlsrv_legacy') ?? config('database.connections.sqlsrv');
            if (is_array($baseConfig)) {
                $baseConfig['host'] = $server;
                config(["database.connections.{$connection}" => $baseConfig]);
            }
        }

        // Final fallback to the default connection if we still don't have one.
        if (!config("database.connections.{$connection}")) {
            $connection = config('database.default');
        }

        $databases = DatabaseHelper::getDatabaseList($connection);

        $fallback = $this->configuredDatabaseList();

        $databases = collect($databases ?? [])
            ->merge($fallback)
            ->filter(fn($db) => is_string($db) && $db !== '')
            ->unique()
            ->values()
            ->all();

        // Add "All" option at the top
        array_unshift($databases, 'All');

        return response()->json([
            'server' => $server,
            'databases' => array_map(fn($db) => ['id' => $db, 'name' => $db], $databases),
        ]);
    }

    /**
     * Test connection to server
     */
    public function testConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server' => 'required|string',
            'host' => 'required|string',
            'port' => 'required|integer',
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $success = DatabaseHelper::testServerConnection(
            $validated['host'],
            $validated['port'],
            $validated['username'],
            $validated['password']
        );

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Connection successful' : 'Connection failed',
        ]);
    }

    /**
     * Fallback database list from configuration (LEGACY_DB_DATABASES + default legacy DB)
     */
    private function configuredDatabaseList(): array
    {
        $options = config('database.legacy_databases', []);
        $options = is_array($options) ? array_filter(array_map('trim', $options)) : [];

        $defaultLegacyDb = (string) (config('database.connections.sqlsrv_legacy.database') ?? '');
        if ($defaultLegacyDb !== '' && ! in_array($defaultLegacyDb, $options, true)) {
            array_unshift($options, $defaultLegacyDb);
        }

        return array_values(array_unique($options));
    }
}
