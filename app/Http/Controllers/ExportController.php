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
        
        // Export dibuka untuk semua user yang login; pembatasan hanya untuk aksi hapus.

        $baseTableName = $mapping->table_name;
        $connection = $mapping->target_connection
            ?? $mapping->connection
            ?? config('database.default');
        $this->ensureLegacyConnectionConfigured($connection);

        // Jika tabel tidak ada di koneksi terpilih tapi ada di legacy, gunakan legacy
        if (!Schema::connection($connection)->hasTable($baseTableName) && Schema::connection('sqlsrv_legacy')->hasTable($baseTableName)) {
            $connection = 'sqlsrv_legacy';
        }

        // Detect period column from actual table schema FIRST
        $actualTableColumns = Schema::connection($connection)->getColumnListing($baseTableName);
        $periodColumn = null;
        foreach (['period', 'period_date', 'periode', 'period_dt', 'perioddate', 'periodo', 'per_date', 'period_code', 'priod'] as $candidate) {
            if (in_array($candidate, $actualTableColumns)) {
                $periodColumn = $candidate;
                break;
            }
        }

        // Detect index column (upload_index or index_id)
        $indexColumn = null;
        if (in_array('index_id', $actualTableColumns, true)) {
            $indexColumn = 'index_id';
        } elseif (in_array('upload_index', $actualTableColumns, true)) {
            $indexColumn = 'upload_index';
        }

        // Get period filter from request (could be period_date or other names, but we filter by detected column)
        $periodFilter = $request->query('period_date') ?? $request->query('period');
        $indexIdFilter = $request->query('index_id');
        $rowLimit = $request->query('limit') ? (int) $request->query('limit') : null;

        $tableName = $baseTableName;
        $activeRun = null;
        $legacyActiveIndex = null;
        
        // Priority 1: Filter by period if available
        if ($periodFilter && $periodColumn) {
            $periodRun = DB::table('mapping_upload_runs')
                ->where('mapping_index_id', $mapping->id)
                ->where('period_date', $periodFilter)
                ->orderByDesc('upload_index')
                ->first();

            if ($periodRun) {
                $activeRun = (object) $periodRun;
            }
        }
        
        // Priority 2: Filter by index_id if no period
        if (!$activeRun && $indexIdFilter && $indexColumn) {
            // This will be used later in the filter
        }

        // Fallback to active run (latest) if none matched the requested period
        if (! $activeRun) {
            $activeRun = $uploadIndexService->getActiveRun($mapping->id, null);
        }

        if (! $activeRun) {
            $legacyActiveIndex = $this->getActiveUploadIndexFromLegacy($baseTableName, $connection, $periodFilter);
        }

        if ($activeRun && $activeRun->period_date) {
            $candidate = $uploadIndexService->buildVersionTableName($baseTableName, $activeRun->period_date, (int) $activeRun->upload_index);
            if (Schema::connection($connection)->hasTable($candidate)) {
                $tableName = $candidate;
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

        // Note: $actualTableColumns already fetched above
        $versionColumn = in_array('index_id', $actualTableColumns, true)
            ? 'index_id'
            : (in_array('upload_index', $actualTableColumns, true) ? 'upload_index' : null);
        if ($versionColumn) {
            $columnMapping = array_filter($columnMapping, fn($col) => $col !== $versionColumn);
        }

        // Note: $periodColumn already detected above
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
        
        $uploadIndexFilter = $activeRun?->upload_index ?? $legacyActiveIndex;
        if (in_array('upload_index', $actualTableColumns, true) && $uploadIndexFilter !== null) {
            if ($tableName === $baseTableName) {
                $query->where(function ($q2) use ($uploadIndexFilter) {
                    $q2->where('upload_index', $uploadIndexFilter)
                        ->orWhereNull('upload_index');
                });
            } else {
                $query->where('upload_index', $uploadIndexFilter);
            }
        }
        
        // Filter by index_id if no period column and index_id filter provided
        if (!$periodColumn && !$periodFilter && $indexIdFilter && $indexColumn) {
            $query->where($indexColumn, $indexIdFilter);
        }
        
        if (!$this->userHasRole($user, 'superuser')) {
            if (in_array('division_id', $actualTableColumns)) {
                $query->where('division_id', $user->division_id);
            }
        }
        
        // Filter by period if provided and period column was detected
        if ($periodColumn && $periodFilter) {
            $query->whereDate($periodColumn, $periodFilter);
        }
        
        // Apply row limit if no period and no index_id filters (safety limit)
        if (!$periodFilter && !$indexIdFilter && $rowLimit) {
            $query->limit($rowLimit);
        }

        $includeBaseFallback = $tableName !== $baseTableName
            && Schema::connection($connection)->hasTable($baseTableName);
        $unversionedQuery = null;
        if ($includeBaseFallback) {
            $baseColumns = Schema::connection($connection)->getColumnListing($baseTableName);
            $baseSelectColumns = array_values(array_filter($selectColumns, function ($col) use ($baseColumns) {
                return in_array($col, $baseColumns, true);
            }));
            $unversionedQuery = DB::connection($connection)->table($baseTableName)->select($baseSelectColumns);
            if (in_array('upload_index', $baseColumns, true)) {
                $unversionedQuery->whereNull('upload_index');
            }
            if (!$this->userHasRole($user, 'superuser') && in_array('division_id', $baseColumns, true)) {
                $unversionedQuery->where('division_id', $user->division_id);
            }
            if ($periodColumn && in_array($periodColumn, $baseColumns, true) && $periodFilter) {
                $unversionedQuery->whereDate($periodColumn, $periodFilter);
            }
            // Filter by index_id if applicable
            if (!$periodColumn && !$periodFilter && $indexIdFilter && $indexColumn && in_array($indexColumn, $baseColumns, true)) {
                $unversionedQuery->where($indexColumn, $indexIdFilter);
            }
        }

        // Count first to decide output mode
        $totalCount = (clone $query)->count();
        if ($unversionedQuery) {
            $totalCount += (clone $unversionedQuery)->count();
        }
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

        return response()->streamDownload(function () use ($query, $unversionedQuery, $headerRow, $columnMapping, $actualTableColumns, $chunkSize, $orderColumn, $periodColumn) {
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
            if ($unversionedQuery) {
                $unversionedQuery->orderBy($orderColumn)->chunk($chunkSize, function ($rows) use ($writer, $columnMapping, $actualTableColumns, $periodColumn) {
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
            }

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
        // Filter out versioning columns (index_id/upload_index) so template starts from col A without gaps
        $columns = $mapping->columns->filter(function ($col) {
            return !in_array($col->table_column_name, ['index_id', 'upload_index'], true);
        });

        if ($columns->isEmpty()) {
            return back()->with('error', 'Tidak ada kolom yang di-mapping untuk format ini.');
        }

        // Build ordered map without gaps and without version columns
        $orderedColumns = $columns->sortBy(function ($col) {
            return $this->columnLetterToIndex($col->excel_column_index);
        })->values();

        // Prepare contiguous header row starting at column A
        $headerCells = $orderedColumns->pluck('table_column_name')->all();

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

    private function detectLegacyIndexTable(string $baseTable, string $connection): ?array
    {
        $indexTable = $baseTable . '_INDEX';
        if (! Schema::connection($connection)->hasTable($indexTable)) {
            return null;
        }

        $columns = Schema::connection($connection)->getColumnListing($indexTable);
        $indexColumn = collect($columns)->first(function ($col) {
            return in_array(strtolower($col), ['upload_index', 'index', 'idx'], true);
        });
        $activeColumn = collect($columns)->first(function ($col) {
            return in_array(strtolower($col), ['is_active', 'active', 'status'], true);
        });
        if (! $indexColumn || ! $activeColumn) {
            return null;
        }

        $periodColumn = collect($columns)->first(function ($col) {
            return in_array(strtolower($col), ['period_date', 'period', 'periode', 'period_dt', 'periodo', 'per_date', 'period_code'], true);
        });

        return [
            'table' => $indexTable,
            'index_column' => $indexColumn,
            'active_column' => $activeColumn,
            'period_column' => $periodColumn,
        ];
    }

    private function getActiveUploadIndexFromLegacy(string $baseTable, string $connection, ?string $periodDate = null): ?int
    {
        $meta = $this->detectLegacyIndexTable($baseTable, $connection);
        if (! $meta) {
            return null;
        }

        try {
            $conn = DB::connection($connection);
            $grammar = $conn->getQueryGrammar();

            $row = $conn->table($meta['table'])
                ->when($periodDate && $meta['period_column'], function ($q) use ($meta, $periodDate) {
                    $q->whereDate($meta['period_column'], $periodDate);
                })
                ->where(function ($q) use ($meta, $grammar) {
                    $wrapped = $grammar->wrap($meta['active_column']);
                    $q->where($meta['active_column'], 1)
                        ->orWhere($meta['active_column'], true)
                        ->orWhereRaw("LOWER({$wrapped}) = 'active'");
                })
                ->orderByDesc($meta['index_column'])
                ->first();

            if ($row && isset($row->{$meta['index_column']}) && is_numeric($row->{$meta['index_column']})) {
                return (int) $row->{$meta['index_column']};
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
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
}
