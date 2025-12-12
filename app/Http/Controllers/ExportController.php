<?php

namespace App\Http\Controllers;

use App\Models\MappingIndex;
use App\Services\UploadIndexService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class ExportController extends Controller
{
    public function export(Request $request, $mappingId)
    {
        // Long-running export: lift limits a bit
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        /** @var UploadIndexService $uploadIndexService */
        $uploadIndexService = app(UploadIndexService::class);

        $user = Auth::user(); 
        $mapping = MappingIndex::with('columns')->findOrFail($mappingId);
        $periodFilter = $request->query('period_date');

        // Export dibuka untuk semua user yang login; pembatasan hanya untuk aksi hapus.

        $baseTableName = $mapping->table_name;
        $connection = $mapping->target_connection
            ?? $mapping->connection
            ?? config('database.default');

        // Jika tabel tidak ada di koneksi terpilih tapi ada di legacy, gunakan legacy
        if (!Schema::connection($connection)->hasTable($baseTableName) && Schema::connection('sqlsrv_legacy')->hasTable($baseTableName)) {
            $connection = 'sqlsrv_legacy';
        }

        $tableName = $baseTableName;
        $activeRun = null;
        // Resolve run for requested period (latest)
        if ($periodFilter) {
            $periodRun = DB::table('mapping_upload_runs')
                ->where('mapping_index_id', $mapping->id)
                ->where('period_date', $periodFilter)
                ->orderByDesc('upload_index')
                ->first();

            if ($periodRun) {
                $activeRun = (object) $periodRun;
                $candidate = $uploadIndexService->buildVersionTableName($baseTableName, $periodRun->period_date, (int) $periodRun->upload_index);
                if (Schema::connection($connection)->hasTable($candidate)) {
                    $tableName = $candidate;
                }
            }
        }

        // Fallback to active run (latest) if none matched the requested period
        if (! $activeRun) {
            $activeRun = $uploadIndexService->getActiveRun($mapping->id, null);
            if ($activeRun && $activeRun->period_date) {
                $candidate = $uploadIndexService->buildVersionTableName($baseTableName, $activeRun->period_date, (int) $activeRun->upload_index);
                if (Schema::connection($connection)->hasTable($candidate)) {
                    $tableName = $candidate;
                }
            }
        }
        
        // Get column mapping: excel_column => table_column_name
        $columnMapping = $mapping->columns
            ->sortBy(fn($col) => $this->columnLetterToIndex($col->excel_column_index))
            ->pluck('table_column_name', 'excel_column_index')
            ->toArray();

        if (empty($columnMapping)) {
            return back()->with('error', 'Tidak ada kolom yang di-mapping untuk format ini.');
        }

        $actualTableColumns = Schema::connection($connection)->getColumnListing($tableName);

        // Determine period column (period_date or legacy aliases)
        $periodColumn = null;
        foreach (['period', 'period_date', 'periode', 'period_dt', 'perioddate'] as $candidate) {
            if (in_array($candidate, $actualTableColumns)) {
                $periodColumn = $candidate;
                break;
            }
        }

        // Remove duplicate period-like columns except the chosen one
        if ($periodColumn) {
            $periodAliases = ['period', 'period_date', 'periode', 'period_dt', 'perioddate'];
            $periodAliases = array_diff($periodAliases, [$periodColumn]);
            $columnMapping = array_filter($columnMapping, function ($dbCol) use ($periodAliases) {
                return !in_array($dbCol, $periodAliases, true);
            });
            // Avoid duplicate column: remove the chosen period column from the mapped list; we'll add it once below.
            $columnMapping = array_filter($columnMapping, function ($dbCol) use ($periodColumn) {
                return $dbCol !== $periodColumn;
            });
        }

        // Recompute valid columns after filtering
        $validColumns = array_intersect(array_values($columnMapping), $actualTableColumns);

        if (empty($validColumns)) {
            return back()->with('error', 'Konfigurasi mapping tidak sesuai dengan skema tabel. Tidak ada kolom valid yang bisa diexport.');
        }

        // Include period, created_at, updated_at in select if they exist
        $selectColumns = $validColumns;
        if ($periodColumn) {
            $selectColumns[] = $periodColumn;
        }
        if (in_array('created_at', $actualTableColumns)) {
            $selectColumns[] = 'created_at';
        }
        if (in_array('updated_at', $actualTableColumns)) {
            $selectColumns[] = 'updated_at';
        }

        $query = DB::connection($connection)->table($tableName)->select($selectColumns);
        
        if (in_array('upload_index', $actualTableColumns, true) && $activeRun) {
            $query->where('upload_index', $activeRun->upload_index);
        }
        
        if (!$this->userHasRole($user, 'super-admin')) {
            if (in_array('division_id', $actualTableColumns)) {
                $query->where('division_id', $user->division_id);
            }
        }
        
        // TAMBAHAN: Filter by period if provided
        if ($periodColumn && $request->has('period_date') && $request->period_date) {
            $query->whereDate($periodColumn, $request->period_date);
        }

        // Count first to decide output mode
        $totalCount = (clone $query)->count();
        if ($totalCount === 0) {
            return back()->with('error', 'Tidak ada data untuk diexport.');
        }

        $headerRow = [];
        foreach (array_keys($columnMapping) as $excelCol) {
            $dbColumn = $columnMapping[$excelCol];
            $headerRow[] = ucwords(str_replace('_', ' ', $dbColumn));
        }
        if ($periodColumn) {
            $headerRow[] = 'Period';
        }
        if (in_array('created_at', $actualTableColumns)) {
            $headerRow[] = 'Created At';
        }
        if (in_array('updated_at', $actualTableColumns)) {
            $headerRow[] = 'Updated At';
        }

        $chunkSize = 2000;
        // Prefer ordering by primary key if present to ensure deterministic chunking
        $orderColumn = in_array('id', $actualTableColumns, true)
            ? 'id'
            : ($selectColumns[0] ?? $validColumns[0] ?? 'id');
        $fileName = $mapping->code . '_' . date('Y-m-d_His') . '.xlsx';

        return response()->streamDownload(function () use ($query, $headerRow, $columnMapping, $actualTableColumns, $chunkSize, $orderColumn, $periodColumn) {
            $writer = new Writer();
            $writer->openToFile('php://output');

            $headerStyle = (new Style())->setFontBold();
            $writer->addRow(Row::fromValues($headerRow, $headerStyle));

            // SQL Server chunk requires explicit order
            $query->orderBy($orderColumn)->chunk($chunkSize, function ($rows) use ($writer, $columnMapping, $actualTableColumns, $periodColumn) {
                foreach ($rows as $item) {
                    $rowData = [];
                    foreach (array_keys($columnMapping) as $excelCol) {
                        $dbColumn = $columnMapping[$excelCol];
                        $rowData[] = $item->{$dbColumn} ?? '';
                    }
                    if ($periodColumn) {
                        $rowData[] = $item->{$periodColumn} ?? '';
                    }
                    if (in_array('created_at', $actualTableColumns)) {
                        $rowData[] = $item->created_at ?? '';
                    }
                    if (in_array('updated_at', $actualTableColumns)) {
                        $rowData[] = $item->updated_at ?? '';
                    }

                    $writer->addRow(Row::fromValues($rowData));
                }
            });

            $writer->close();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export header-only template so users can fill and re-upload data.
     */
    public function exportTemplate(Request $request, $mappingId)
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $mapping = MappingIndex::with('columns')->findOrFail($mappingId);
        $columns = $mapping->columns;

        if ($columns->isEmpty()) {
            return back()->with('error', 'Tidak ada kolom yang di-mapping untuk format ini.');
        }

        // Build ordered map: numeric index (0-based) => column model
        $orderedColumns = [];
        $maxIndex = 0;
        foreach ($columns as $col) {
            $index = $this->columnLetterToIndex($col->excel_column_index);
            $orderedColumns[$index] = $col;
            $maxIndex = max($maxIndex, $index);
        }

        // Prepare header row respecting gaps between Excel columns (A, C, etc.)
        $headerCells = [];
        for ($i = 0; $i <= $maxIndex; $i++) {
            $headerCells[] = isset($orderedColumns[$i])
                ? $orderedColumns[$i]->table_column_name
                : '';
        }

        $headerRowNumber = max(1, (int) $mapping->header_row);
        $blankRow = array_fill(0, max(1, count($headerCells)), '');
        $fileName = $mapping->code . '_template_' . date('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($headerCells, $blankRow, $headerRowNumber, $mapping, $orderedColumns) {
            $writer = new Writer();
            $writer->openToFile('php://output');

            // Sheet 1: Template
            $writer->getCurrentSheet()->setName('Template');

            // Add empty rows until the configured header row (if header_row > 1)
            for ($row = 1; $row < $headerRowNumber; $row++) {
                $writer->addRow(Row::fromValues($blankRow));
            }

            $headerStyle = (new Style())->setFontBold();
            $writer->addRow(Row::fromValues($headerCells, $headerStyle));

            // Sheet 2: Petunjuk (metadata + column guide)
            $writer->addNewSheetAndMakeItCurrent();
            $writer->getCurrentSheet()->setName('Petunjuk');

            $writer->addRow(Row::fromValues(['Template format', $mapping->description ?? $mapping->code]));
            $writer->addRow(Row::fromValues(['Kode format', $mapping->code]));
            $writer->addRow(Row::fromValues(['Tabel tujuan', $mapping->table_name]));
            $writer->addRow(Row::fromValues(['Baris header', $headerRowNumber]));
            $writer->addRow(Row::fromValues(['Mulai isi data di baris', $headerRowNumber + 1]));
            $writer->addRow(Row::fromValues(['Catatan', 'Jangan ubah urutan kolom atau isi baris header.']));
            $writer->addRow(Row::fromValues([])); // spacer
            $writer->addRow(Row::fromValues(['Kolom', 'Posisi Excel', 'Tipe Data', 'Wajib?', 'Kunci Unik?'], $headerStyle));

            ksort($orderedColumns);
            foreach ($orderedColumns as $col) {
                $writer->addRow(Row::fromValues([
                    $col->table_column_name,
                    strtoupper($col->excel_column_index),
                    ucfirst($col->data_type ?? 'string'),
                    $col->is_required ? 'Ya' : 'Opsional',
                    $col->is_unique_key ? 'Ya' : 'Tidak',
                ]));
            }

            $writer->close();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Convert Excel column letters (A, Z, AA, AB, etc.) into zero-based numeric index.
     */
    private function columnLetterToIndex(string $column): int
    {
        $column = strtoupper(trim($column));

        if ($column === '') {
            return 0;
        }

        $index = 0;
        $length = strlen($column);

        for ($i = 0; $i < $length; $i++) {
            $char = $column[$i];
            if ($char < 'A' || $char > 'Z') {
                continue;
            }
            $index = ($index * 26) + (ord($char) - ord('A') + 1);
        }

        return max(0, $index - 1);
    }
}
