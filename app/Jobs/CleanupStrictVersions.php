<?php

namespace App\Jobs;

use App\Services\UploadIndexService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CleanupStrictVersions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $mappingId;
    private string $periodDate;
    private string $baseTable;
    private string $dbConnection;

    public function __construct(int $mappingId, string $periodDate, string $baseTable, string $connection)
    {
        $this->mappingId = $mappingId;
        $this->periodDate = $periodDate;
        $this->baseTable = $baseTable;
        $this->dbConnection = $connection;
    }

    public function handle(UploadIndexService $uploadIndexService): void
    {
        // Grace period before dropping, to allow rollback/inspection
        $cooldown = now()->subMinutes(10);
        Log::info('Strict cleanup started', [
            'mapping_id' => $this->mappingId,
            'period_date' => $this->periodDate,
        ]);

        $runs = DB::table('mapping_upload_runs')
            ->where('mapping_index_id', $this->mappingId)
            ->where('period_date', $this->periodDate)
            ->where('status', 'inactive')
            ->whereNull('dropped_at')
            ->whereNotNull('inactivated_at')
            ->where('inactivated_at', '<=', $cooldown)
            ->orderByDesc('upload_index')
            ->get();

        if ($runs->isEmpty()) {
            return;
        }

        // Drop ALL inactive versions that passed cooldown (active version stays intact)
        foreach ($runs as $run) {
            $table = $uploadIndexService->buildVersionTableName($this->baseTable, $this->periodDate, (int) $run->upload_index);
            try {
                if (Schema::connection($this->dbConnection)->hasTable($table)) {
                    Schema::connection($this->dbConnection)->drop($table);
                    Log::info('Strict version dropped', [
                        'mapping_id' => $this->mappingId,
                        'period_date' => $this->periodDate,
                        'upload_index' => $run->upload_index,
                        'table' => $table,
                    ]);
                }

                DB::table('mapping_upload_runs')
                    ->where('id', $run->id)
                    ->update([
                        'dropped_at' => now(),
                        'updated_at' => now(),
                    ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to drop strict version', [
                    'mapping_id' => $this->mappingId,
                    'period_date' => $this->periodDate,
                    'upload_index' => $run->upload_index,
                    'table' => $table,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
