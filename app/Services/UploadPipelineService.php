<?php

namespace App\Services;

use App\Models\MappingIndex;
use App\Models\UploadRun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadPipelineService
{
    public function run(UploadRun $run, UploadIndexService $uploadIndexService): void
    {
        // Rebuild pseudo-request payload from stored data and reuse controller pipeline.
        $mappingId = $run->mapping_index_id;
        $uploadMode = $run->upload_mode;
        $sheetName = $run->sheet_name;
        $periodDate = $run->period_date ?? null;
        $selectedColumns = $run->selected_columns;

        // Ensure auth context for logging and permissions
        if ($run->user_id) {
            Auth::loginUsingId($run->user_id);
        }

        $mapping = MappingIndex::with('columns')->find($mappingId);
        if (!$mapping) {
            throw new \RuntimeException("Format tidak ditemukan (ID {$mappingId}).");
        }

        // Use stored xlsx path
        $excelPath = $run->stored_xlsx_path;
        if (!file_exists($excelPath)) {
            throw new \RuntimeException("File upload tidak ditemukan di path: {$excelPath}");
        }

        // Build UploadedFile so validation (File::types) still works
        $uploadedFile = new \Illuminate\Http\UploadedFile(
            $excelPath,
            $run->file_name ?? basename($excelPath),
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        // Fake a request and call the existing uploadData pipeline synchronously in the job
        $request = new \Illuminate\Http\Request([
            'mapping_id' => $mappingId,
            'upload_mode' => $uploadMode,
            'sheet_name' => $sheetName,
            'period_date' => $periodDate,
            'selected_columns' => $selectedColumns,
            'run_id' => $run->id,
        ]);
        $request->setMethod('POST');
        $request->files->set('data_file', $uploadedFile);
        $request->setUserResolver(fn () => Auth::user());

        $response = app(\App\Http\Controllers\MappingController::class)->uploadData($request);

        // If controller returns JSON with success=false, treat as failure so the job marks the run failed.
        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $data = $response->getData();
            if (isset($data->success) && $data->success === false) {
                $message = $data->message ?? 'Upload gagal.';
                throw new \RuntimeException($message);
            }
        }

        // Remove temp file after processing to avoid pile-up
        if (file_exists($excelPath)) {
            @unlink($excelPath);
        }
    }
}
