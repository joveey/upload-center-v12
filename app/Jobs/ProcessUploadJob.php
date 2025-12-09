<?php

namespace App\Jobs;

use App\Models\UploadRun;
use App\Services\UploadIndexService;
use App\Services\UploadPipelineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uploadRunId;

    public function __construct(int $uploadRunId)
    {
        $this->uploadRunId = $uploadRunId;
    }

    public function handle(UploadPipelineService $pipeline, UploadIndexService $uploadIndexService): void
    {
        $run = UploadRun::find($this->uploadRunId);
        if (! $run) {
            Log::warning('UploadRun not found for job', ['run_id' => $this->uploadRunId]);
            return;
        }

        $run->update([
            'status' => 'processing',
            'progress_percent' => 5,
            'started_at' => now(),
        ]);

        try {
            $pipeline->run($run, $uploadIndexService);

            $run->update([
                'status' => 'success',
                'progress_percent' => 100,
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $friendly = $this->friendlyMessage($e);
            Log::error('Upload job failed', [
                'run_id' => $run->id,
                'error' => $friendly,
            ]);
            $run->update([
                'status' => 'failed',
                'message' => $friendly,
                'progress_percent' => 0,
                'finished_at' => now(),
            ]);
            throw $e;
        }
    }

    private function friendlyMessage(\Throwable $e): string
    {
        $raw = $e->getMessage();
        $lower = strtolower($raw);

        if (str_contains($lower, 'duplicate key')) {
            return 'Gagal upload: ada data duplikat pada kolom unik, bersihkan duplikat lalu coba lagi.';
        }

        if (str_contains($lower, 'incorrect syntax near')) {
            return 'Gagal upload: ada masalah sintaks, cek kembali pemetaan kolom atau format data.';
        }

        if (str_contains($lower, 'bulk load') || str_contains($lower, 'bulk insert')) {
            return 'Gagal upload: import ke staging tidak bisa dibaca, pastikan format kolom dan delimiter sesuai.';
        }

        // Fallback generic tanpa kode/SQL mentah
        return 'Gagal upload: ' . (str_contains($raw, 'SQLSTATE') ? 'terjadi kesalahan pada database.' : $raw);
    }
}
