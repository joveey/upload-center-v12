<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UploadIndexService
{
    private string $controlConnection;

    public function __construct(?string $controlConnection = null)
    {
        $this->controlConnection = $controlConnection ?? config('database.default');
    }

    /**
     * Start a new upload run for a mapping with a lock-safe, incrementing index.
     */
    public function beginRun(int $mappingId, ?int $userId = null, ?string $periodDate = null): object
    {
        $connection = DB::connection($this->controlConnection);

        return $connection->transaction(function () use ($connection, $mappingId, $userId, $periodDate) {
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
            $maxIndex = (int) $connection->table('mapping_upload_runs')
                ->where('mapping_index_id', $mappingId)
                ->when($periodDate !== null, fn($q) => $q->where('period_date', $periodDate), fn($q) => $q->whereNull('period_date'))
                ->lockForUpdate()
                ->max('upload_index');

            $nextIndex = $maxIndex + 1;
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
     * Atomically activate a completed run and deactivate previous active runs.
     */
    public function activateRun(object $run): void
    {
        $connection = DB::connection($this->controlConnection);

        $connection->transaction(function () use ($connection, $run) {
            $mappingId = $run->mapping_index_id;
            $runId = $run->id;
            $periodDate = $run->period_date;

            // Deactivate any currently active run for this mapping
            $connection->table('mapping_upload_runs')
                ->where('mapping_index_id', $mappingId)
                ->when($periodDate !== null, fn($q) => $q->where('period_date', $periodDate), fn($q) => $q->whereNull('period_date'))
                ->where('status', 'active')
                ->update([
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
            ]);
        });
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
        return DB::connection($this->controlConnection)
            ->table('mapping_upload_runs')
            ->where('mapping_index_id', $mappingId)
            ->where('status', 'active')
            ->when($periodDate !== null, fn($q) => $q->where('period_date', $periodDate))
            ->orderByDesc('period_date')
            ->orderByDesc('upload_index')
            ->first();
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
