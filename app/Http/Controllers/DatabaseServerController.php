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
        $server = $request->query('server');
        
        if (!$server) {
            return response()->json(['error' => 'Server parameter required'], 422);
        }

        // Get connection config for this server
        $connection = "databases_server_{$server}";
        
        // For now, use sqlsrv (local testing)
        // Later will be dynamic based on server
        $connection = 'sqlsrv';

        $databases = DatabaseHelper::getDatabaseList($connection);

        // Merge with fallback list from env if provided
        $fallback = array_values(array_filter(array_map('trim', explode(',', env('LEGACY_DB_DATABASES', '')))));
        if (!empty($fallback)) {
            $databases = collect($databases)
                ->merge($fallback)
                ->unique()
                ->values()
                ->all();
        }

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
}
