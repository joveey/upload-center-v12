<?php

namespace App\Providers;

use Illuminate\Database\Connectors\SqlServerConnector;
use Illuminate\Database\SqlServerConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use PDO;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::extend('sqlsrv', function ($config, $name) {
            $config['name'] = $name;

            $connector = new SqlServerConnector();

            // SQL Server driver on this environment rejects ATTR_STRINGIFY_FETCHES,
            // so strip it from the default PDO options.
            $connector->setDefaultOptions([
                PDO::ATTR_CASE => PDO::CASE_NATURAL,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
            ]);

            $pdo = $connector->connect($config);

            return new SqlServerConnection(
                $pdo,
                $config['database'] ?? '',
                $config['prefix'] ?? '',
                $config
            );
        });
    }
}
