<?php

namespace App\Http\Controllers;

use App\Models\MappingIndex;
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

        $user = Auth::user(); 
        $mapping = MappingIndex::with('columns')->findOrFail($mappingId);

        // Export dibuka untuk semua user yang login; pembatasan hanya untuk aksi hapus.

        $tableName = $mapping->table_name;
        $connection = $mapping->target_connection
            ?? $mapping->connection
            ?? config('database.default');

        // Jika tabel tidak ada di koneksi terpilih tapi ada di legacy, gunakan legacy
        if (!Schema::connection($connection)->hasTable($tableName) && Schema::connection('sqlsrv_legacy')->hasTable($tableName)) {
            $connection = 'sqlsrv_legacy';
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
        $validColumns = array_intersect(array_values($columnMapping), $actualTableColumns);

        if (empty($validColumns)) {
            return back()->with('error', 'Konfigurasi mapping tidak sesuai dengan skema tabel. Tidak ada kolom valid yang bisa diexport.');
        }

        // Include period_date, created_at, updated_at in select if they exist
        $selectColumns = $validColumns;
        if (in_array('period_date', $actualTableColumns)) {
            $selectColumns[] = 'period_date';
        }
        if (in_array('created_at', $actualTableColumns)) {
            $selectColumns[] = 'created_at';
        }
        if (in_array('updated_at', $actualTableColumns)) {
            $selectColumns[] = 'updated_at';
        }

        $query = DB::connection($connection)->table($tableName)->select($selectColumns);
        
        if (!$this->userHasRole($user, 'super-admin')) {
            if (in_array('division_id', $actualTableColumns)) {
                $query->where('division_id', $user->division_id);
            }
        }
        
        // TAMBAHAN: Filter by period if provided
        if ($request->has('period_date') && $request->period_date && in_array('period_date', $actualTableColumns)) {
            $query->where('period_date', $request->period_date);
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
        if (in_array('period_date', $actualTableColumns)) {
            $headerRow[] = 'Period Date';
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

        return response()->streamDownload(function () use ($query, $headerRow, $columnMapping, $actualTableColumns, $chunkSize, $orderColumn) {
            $writer = new Writer();
            $writer->openToFile('php://output');

            $headerStyle = (new Style())->setFontBold();
            $writer->addRow(Row::fromValues($headerRow, $headerStyle));

            // SQL Server chunk requires explicit order
            $query->orderBy($orderColumn)->chunk($chunkSize, function ($rows) use ($writer, $columnMapping, $actualTableColumns) {
                foreach ($rows as $item) {
                    $rowData = [];
                    foreach (array_keys($columnMapping) as $excelCol) {
                        $dbColumn = $columnMapping[$excelCol];
                        $rowData[] = $item->{$dbColumn} ?? '';
                    }
                    if (in_array('period_date', $actualTableColumns)) {
                        $rowData[] = $item->period_date ?? '';
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
