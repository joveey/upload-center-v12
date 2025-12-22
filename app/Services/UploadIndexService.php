<?php

namespace App\Services;

use App\Models\MappingIndex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class UploadIndexService
{
    private string $controlConnection;

    public function __construct(?string $controlConnection = null)
    {
        $control = $controlConnection ?? config('database.control_connection');
        $isLegacy = fn($name) => $name === 'sqlsrv_legacy' || str_starts_with((string) $name, 'legacy_');

        if (! $control || ! config("database.connections.{$control}") || $isLegacy($control)) {
            if (config('database.connections.sqlsrv')) {
                $control = 'sqlsrv';
            } else {
                $fallback = config('database.default');
                $control = $isLegacy($fallback) && config('database.connections.sqlsrv') ? 'sqlsrv' : $fallback;
            }
        }
        $this->controlConnection = $control;
    }

    /**
     * Start a new upload run for a mapping with a lock-safe, incrementing index.
     * Optionally seed the max index with an external baseline (e.g. legacy *_INDEX table).
     */
    public function beginRun(int $mappingId, ?int $userId = null, ?string $periodDate = null, ?int $baselineMaxIndex = null): object
    {
        $connection = DB::connection($this->controlConnection);

        return $connection->transaction(function () use ($connection, $mappingId, $userId, $periodDate, $baselineMaxIndex) {
            $driver = $connection->getDriverName();
            $periodDate = $periodDate ? date('Y-m-d', strtotime($periodDate)) : null;

            // Use app lock on SQL Server to serialize index allocation per mapping
            if ($driver === 'sqlsrv') {
                $suffix = $periodDate ? "_{$periodDate}" : '';
                $resource = "upload_index_{$mappingId}{$suffix}";
                $connection->statement(
                    "EXEC sp_getapplock @Resource = ?, @LockMode = 'Exclusive', @LockOwner = 'Transaction', @LockTimeout = 60000",
                    [$resource]
                );
            }

            // Lock rows for this mapping to compute the next index safely
            $maxIndexRaw = $connection->table('mapping_upload_runs')
                ->where('mapping_index_id', $mappingId)
                ->when(
                    $periodDate !== null,
                    fn($q) => $q->where('period_date', $periodDate),
                    fn($q) => $q->whereNull('period_date')
                )
                ->lockForUpdate()
                ->max('upload_index');

            if ($maxIndexRaw === null && $periodDate === null) {
                $maxIndexRaw = $connection->table('mapping_upload_runs')
                    ->where('mapping_index_id', $mappingId)
                    ->lockForUpdate()
                    ->max('upload_index');
            }

            $maxIndex = (int) ($maxIndexRaw ?? 0);

            if ($baselineMaxIndex !== null) {
                $maxIndex = max($maxIndex, (int) $baselineMaxIndex);
            }

            $nextIndex = $maxIndex + 1;

            $existsQuery = $connection->table('mapping_upload_runs')
                ->where('mapping_index_id', $mappingId)
                ->where('upload_index', $nextIndex)
                ->when(
                    $periodDate !== null,
                    fn($q) => $q->where('period_date', $periodDate),
                    fn($q) => $q->whereNull('period_date')
                );

            if ($existsQuery->exists()) {
                $fallbackMax = (int) ($connection->table('mapping_upload_runs')
                    ->where('mapping_index_id', $mappingId)
                    ->lockForUpdate()
                    ->max('upload_index') ?? 0);
                $nextIndex = $fallbackMax + 1;
            }
            $now = now();

            $id = $connection->table('mapping_upload_runs')->insertGetId([
                'mapping_index_id' => $mappingId,
                'upload_index' => $nextIndex,
                'period_date' => $periodDate,
                'status' => 'pending',
                'created_by' => $userId,
                'started_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $run = $this->getRunById($id);
            Log::info('Upload run created', [
                'mapping_index_id' => $mappingId,
                'upload_index' => $nextIndex,
                'period_date' => $periodDate,
                'run_id' => $id,
            ]);

            return $run;
        });
    }

    /**
     * Start a new upload run using a pre-allocated index (e.g. from legacy *_INDEX identity).
     */
    public function beginRunWithIndex(int $mappingId, int $uploadIndex, ?int $userId = null, ?string $periodDate = null): object
    {
        $connection = DB::connection($this->controlConnection);

        return $connection->transaction(function () use ($connection, $mappingId, $uploadIndex, $userId, $periodDate) {
            $periodDate = $periodDate ? date('Y-m-d', strtotime($periodDate)) : null;
            $now = now();

            $existing = $connection->table('mapping_upload_runs')
                ->where('mapping_index_id', $mappingId)
                ->where('upload_index', $uploadIndex)
                ->when(
                    $periodDate !== null,
                    fn($q) => $q->where('period_date', $periodDate),
                    fn($q) => $q->whereNull('period_date')
                )
                ->first();

            if ($existing) {
                $connection->table('mapping_upload_runs')
                    ->where('id', $existing->id)
                    ->update([
                        'status' => 'pending',
                        'created_by' => $userId,
                        'started_at' => $now,
                        'updated_at' => $now,
                    ]);

                Log::warning('Upload run reused (duplicate index detected)', [
                    'mapping_index_id' => $mappingId,
                    'upload_index' => $uploadIndex,
                    'period_date' => $periodDate,
                    'run_id' => $existing->id,
                ]);

                return $this->getRunById((int) $existing->id);
            }

            $id = $connection->table('mapping_upload_runs')->insertGetId([
                'mapping_index_id' => $mappingId,
                'upload_index' => $uploadIndex,
                'period_date' => $periodDate,
                'status' => 'pending',
                'created_by' => $userId,
                'started_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $run = $this->getRunById($id);
            Log::info('Upload run created (legacy index)', [
                'mapping_index_id' => $mappingId,
                'upload_index' => $uploadIndex,
                'period_date' => $periodDate,
                'run_id' => $id,
            ]);

            return $run;
        });
    }

    /**
     * Atomically activate a completed run and deactivate previous active runs.
     * Scope 'period' keeps other periods active, scope 'all' deactivates everything for the mapping.
     */
    public function activateRun(object $run, string $scope = 'period'): void
    {
        $previousDefault = DB::getDefaultConnection();
        DB::setDefaultConnection($this->controlConnection);
        $connection = DB::connection($this->controlConnection);

        try {
            $connection->transaction(function () use ($connection, $run, $scope) {
                $mappingId = $run->mapping_index_id;
                $runId = $run->id;
                $periodDate = $run->period_date;

                // Deactivate currently active runs for this mapping (scope: period or all)
                $deactivateQuery = $connection->table('mapping_upload_runs')
                    ->where('mapping_index_id', $mappingId)
                    ->where('status', 'active');

                if ($scope !== 'all') {
                    $deactivateQuery->when(
                        $periodDate !== null,
                        fn($q) => $q->where('period_date', $periodDate),
                        fn($q) => $q->whereNull('period_date')
                    );
                }

                $deactivateQuery->update([
                    'status' => 'inactive',
                    'inactivated_at' => now(),
                    'updated_at' => now(),
                ]);

                // Activate this run
                $connection->table('mapping_upload_runs')
                    ->where('id', $runId)
                    ->update([
                        'status' => 'active',
                        'finished_at' => now(),
                        'activated_at' => now(),
                        'updated_at' => now(),
                    ]);

                Log::info('Upload run activated', [
                    'mapping_index_id' => $mappingId,
                    'upload_index' => $run->upload_index,
                    'period_date' => $periodDate,
                    'run_id' => $runId,
                    'scope' => $scope,
                ]);
            });
        } finally {
            DB::setDefaultConnection($previousDefault);
        }
    }

    /**
     * Switch active index in legacy *_INDEX table (replace_all).
     */
    public function switchActiveIndex(int $mappingId, int $newUploadIndex): void
    {
        $previousDefault = DB::getDefaultConnection();
        DB::setDefaultConnection($this->controlConnection);
        try {
            // Explicitly query on control connection using DB facade
            $mapping = DB::connection($this->controlConnection)
                ->table('mapping_indices')
                ->where('id', $mappingId)
                ->first();
            
            if (! $mapping) {
                Log::warning('switchActiveIndex skipped: mapping not found', [
                    'mapping_id' => $mappingId,
                    'upload_index' => $newUploadIndex,
                    'control_connection' => $this->controlConnection,
                ]);
                return;
            }
        } finally {
            DB::setDefaultConnection($previousDefault);
        }

        $connection = $mapping->target_connection
            ?? $mapping->connection
            ?? config('database.default');
        $this->ensureLegacyConnectionConfigured($connection);

        $baseTable = $mapping->table_name;
        if (!Schema::connection($connection)->hasTable($baseTable)
            && Schema::connection('sqlsrv_legacy')->hasTable($baseTable)) {
            $connection = 'sqlsrv_legacy';
        }

        $meta = $this->detectLegacyIndexTable($baseTable, $connection);
        if (! $meta) {
            Log::info('switchActiveIndex skipped: no legacy index table', [
                'mapping_id' => $mappingId,
                'table' => $baseTable,
                'connection' => $connection,
            ]);
            return;
        }

        $conn = DB::connection($connection);
        $now = now();

        $conn->transaction(function () use ($conn, $meta, $newUploadIndex, $now) {
            $statusMode = $meta['status_mode'] ?? 'batch';
            $inactiveValue = $statusMode === 'batch' ? 'inactive' : 0;
            $activeValue = $statusMode === 'batch' ? 'active' : 1;

            $inactivePayload = [$meta['status_column'] => $inactiveValue];
            if ($meta['has_updated_at']) {
                $inactivePayload['updated_at'] = $now;
            }
            $conn->table($meta['table'])->update($inactivePayload);

            $activePayload = [
                $meta['status_column'] => $activeValue,
            ];
            if ($meta['period_column']) {
                $activePayload[$meta['period_column']] = null;
            }
            if ($meta['has_updated_at']) {
                $activePayload['updated_at'] = $now;
            }
            if ($meta['has_created_at']) {
                $activePayload['created_at'] = $now;
            }

            $existing = $conn->table($meta['table'])
                ->where($meta['index_column'], $newUploadIndex);
            if ($meta['period_column']) {
                $existing->whereNull($meta['period_column']);
            }

            $updated = $existing->update($activePayload);
            if ($updated === 0) {
                Log::warning('switchActiveIndex: target index row not found to activate', [
                    'table' => $meta['table'],
                    'index_id' => $newUploadIndex,
                ]);
            }
        });

        Log::info('Legacy index switched', [
            'mapping_id' => $mappingId,
            'table' => $meta['table'],
            'upload_index' => $newUploadIndex,
            'connection' => $connection,
        ]);
    }

    /**
     * Mark a run as failed.
     */
    public function failRun(object $run, ?string $reason = null): void
    {
        $connection = DB::connection($this->controlConnection);

        $connection->table('mapping_upload_runs')
            ->where('id', $run->id)
            ->update([
                'status' => 'failed',
                'finished_at' => now(),
                'updated_at' => now(),
            ]);

        Log::warning('Upload run failed', [
            'mapping_index_id' => $run->mapping_index_id,
            'upload_index' => $run->upload_index,
            'period_date' => $run->period_date ?? null,
            'run_id' => $run->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Get the active run for a mapping (if any).
     */
    public function getActiveRun(int $mappingId, ?string $periodDate = null): ?object
    {
        $query = DB::connection($this->controlConnection)
            ->table('mapping_upload_runs')
            ->where('mapping_index_id', $mappingId)
            ->where('status', 'active')
            ->when($periodDate !== null, fn($q) => $q->where('period_date', $periodDate));

        if ($periodDate !== null) {
            // If a specific period is requested, pick the latest upload_index for that period
            $query->orderByDesc('upload_index');
        } else {
            // Otherwise, show the most recently finished/updated run regardless of period
            $query->orderByDesc('finished_at')
                  ->orderByDesc('updated_at')
                  ->orderByDesc('upload_index');
        }

        return $query->first();
    }

    /**
     * Get all active runs for a mapping (latest per period).
     *
     * @return \Illuminate\Support\Collection|object[]
     */
    public function getActiveRuns(int $mappingId)
    {
        $runs = DB::connection($this->controlConnection)
            ->table('mapping_upload_runs')
            ->where('mapping_index_id', $mappingId)
            ->where('status', 'active')
            ->orderByDesc('period_date')
            ->orderByDesc('upload_index')
            ->get();

        // Keep only the latest upload_index per period_date (including null)
        return $runs->unique(function ($run) {
            return ($run->period_date ?? 'NULL') . '|' . $run->mapping_index_id;
        });
    }

    /**
     * Lightweight helper to get the active upload_index.
     */
    public function getActiveUploadIndex(int $mappingId, ?string $periodDate = null): ?int
    {
        $run = $this->getActiveRun($mappingId, $periodDate);
        return $run ? (int) $run->upload_index : null;
    }

    /**
     * Build strict versioned table name using period + index.
     */
    public function buildVersionTableName(string $baseTable, string $periodDate, int $uploadIndex): string
    {
        $safePeriod = str_replace('-', '_', date('Y-m-d', strtotime($periodDate)));
        return strtolower($baseTable . '__p' . $safePeriod . '__i' . $uploadIndex);
    }

    private function isLegacyConnectionName(?string $connection): bool
    {
        if (! $connection) {
            return false;
        }

        return $connection === 'sqlsrv_legacy' || str_starts_with($connection, 'legacy_');
    }

    private function ensureLegacyConnectionConfigured(string $connection): void
    {
        if (! $this->isLegacyConnectionName($connection) || $connection === 'sqlsrv_legacy') {
            return;
        }

        if (config("database.connections.{$connection}")) {
            return;
        }

        $baseConfig = config('database.connections.sqlsrv_legacy');
        if (! is_array($baseConfig)) {
            return;
        }

        $dbName = substr($connection, strlen('legacy_'));
        if ($dbName === '') {
            return;
        }

        $baseConfig['database'] = $dbName;
        config(["database.connections.{$connection}" => $baseConfig]);
    }

    private function detectLegacyIndexTable(string $baseTable, string $connection): ?array
    {
        $indexTable = $baseTable . '_INDEX';
        if (!Schema::connection($connection)->hasTable($indexTable)) {
            return null;
        }

        $columns = Schema::connection($connection)->getColumnListing($indexTable);
        $indexColumn = collect($columns)->first(function ($col) {
            return in_array(strtolower($col), ['index_id', 'upload_index', 'index', 'idx'], true);
        });
        $statusColumn = collect($columns)->first(function ($col) {
            return strtolower($col) === 'status_batch';
        });
        $statusMode = $statusColumn ? 'batch' : null;

        if (! $statusColumn) {
            $statusColumn = collect($columns)->first(function ($col) {
                return in_array(strtolower($col), ['is_active', 'active', 'status'], true);
            });
            $statusMode = $statusColumn ? 'boolean' : null;
        }

        if (! $indexColumn || ! $statusColumn) {
            return null;
        }

        $periodColumn = collect($columns)->first(function ($col) {
            return in_array(strtolower($col), ['period_date', 'period', 'periode'], true);
        });

        return [
            'table' => $indexTable,
            'index_column' => $indexColumn,
            'status_column' => $statusColumn,
            'status_mode' => $statusMode,
            'period_column' => $periodColumn,
            'has_created_at' => in_array('created_at', $columns, true),
            'has_updated_at' => in_array('updated_at', $columns, true),
        ];
    }

    private function getRunById(int $id): object
    {
        $row = DB::connection($this->controlConnection)
            ->table('mapping_upload_runs')
            ->where('id', $id)
            ->first();

        if (! $row) {
            throw new \RuntimeException("Upload run {$id} tidak ditemukan.");
        }

        return (object) $row;
    }
}
