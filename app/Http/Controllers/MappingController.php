<?php

namespace App\Http\Controllers;

use App\Models\MappingColumn;
use App\Models\MappingIndex;
use App\Models\UploadRun;
use App\Services\QsvExcelConverter;
use App\Services\UploadIndexService;
use App\Jobs\ProcessUploadJob;
use App\Jobs\CleanupStrictVersions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class MappingController extends Controller
{
    /**
     * Menampilkan formulir untuk membuat format dan tabel baru secara manual.
     */
    public function showRegisterForm(): View
    {
        return view('register_form');
    }

    /**
     * Memproses formulir: MEMBUAT TABEL BARU di database dan menyimpan aturan pemetaan.
     */
    public function processRegisterForm(Request $request): RedirectResponse
    {
        Log::info('Memulai proses pendaftaran format & pembuatan tabel baru.');

        // Normalize mapping inputs (trim/case) to avoid false duplicate detection
        $normalizedMappings = collect($request->input('mappings', []))
            ->map(function ($mapping) {
                return [
                    'excel_column' => strtoupper(trim($mapping['excel_column'] ?? '')),
                    'database_column' => strtolower(trim($mapping['database_column'] ?? '')),
                    'is_unique_key' => $mapping['is_unique_key'] ?? null,
                ];
            })
            ->filter(fn ($row) => $row['excel_column'] !== '' && $row['database_column'] !== '')
            ->values()
            ->all();

        $request->merge(['mappings' => $normalizedMappings]);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:mapping_indices,code',
            'table_name' => 'required|string|regex:/^[a-z0-9_]+$/|unique:mapping_indices,table_name',
            'header_row' => 'required|integer|min:1',
            'mappings' => 'required|array|min:1',
            'mappings.*.excel_column' => 'required|string|distinct|max:10',
            'mappings.*.database_column' => ['required', 'string', 'distinct', 'regex:/^[a-z0-9_]+$/', Rule::notIn(['id','period_date'])],
            'mappings.*.is_unique_key' => 'nullable|in:0,1,true,false',
            'upload_mode' => ['nullable', 'in:upsert,strict'],
        ], [
            'name.unique' => 'Nama format ini sudah digunakan.',
            'table_name.regex' => 'Nama tabel hanya boleh berisi huruf kecil, angka, dan underscore (_).',
            'table_name.unique' => 'Nama tabel ini sudah digunakan oleh format lain.',
            'mappings.*.database_column.regex' => 'Nama kolom hanya boleh berisi huruf kecil, angka, dan underscore (_).',
            'mappings.*.database_column.not_in' => 'Nama kolom tidak boleh "id" atau "period_date". Kolom tersebut akan dibuat secara otomatis.',
        ]);

        Log::info('Validasi berhasil.', $validated);
        $tableName = $validated['table_name'];

        if (Schema::hasTable($tableName)) {
            return back()->with('error', "Tabel dengan nama '{$tableName}' sudah ada di database. Silakan gunakan nama lain.")->withInput();
        }

        DB::beginTransaction();
        try {
            // Collect unique key columns
            $uniqueKeyColumns = [];
            foreach ($validated['mappings'] as $mapping) {
                // Handle multiple possible values
                $isUniqueKey = false;
                if (isset($mapping['is_unique_key'])) {
                    $value = $mapping['is_unique_key'];
                    $isUniqueKey = ($value === '1' || $value === 1 || $value === 'true' || $value === true);
                }
                
                if ($isUniqueKey) {
                    $uniqueKeyColumns[] = $mapping['database_column'];
                }
            }
            
            // Buat tabel baru
            Schema::create($tableName, function (Blueprint $table) use ($validated, $uniqueKeyColumns, $tableName) {
                $table->id();
                foreach ($validated['mappings'] as $mapping) {
                    $columnName = $mapping['database_column'];
                    // SQL Server cannot index TEXT types; use string for unique-key columns
                    if (in_array($columnName, $uniqueKeyColumns, true)) {
                        $table->string($columnName, 450)->nullable();
                    } else {
                        $table->text($columnName)->nullable();
                    }
                }
                $table->date('period_date')->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                
                // Add unique constraint if there are unique key columns
                if (!empty($uniqueKeyColumns)) {
                    $table->unique($uniqueKeyColumns, $tableName . '_unique_key');
                    Log::info("Unique constraint created on columns: " . implode(', ', $uniqueKeyColumns));
                }
            });
            Log::info("Tabel '{$tableName}' berhasil dibuat dengan kolom period_date.");

            // Simpan format ke mapping_indices
            $mappingIndex = MappingIndex::create([
                'code' => strtolower(str_replace(' ', '_', $validated['name'])),
                'description' => $validated['name'],
                'table_name' => $tableName,
                'header_row' => $validated['header_row'],
                'division_id' => Auth::user()->division_id,
                'upload_mode' => $validated['upload_mode'] ?? null,
            ]);
            Log::info("Format berhasil disimpan di mapping_indices dengan ID: {$mappingIndex->id}");

            // Simpan mapping kolom dengan status is_unique_key
            foreach ($validated['mappings'] as $mapping) {
                // Handle multiple possible values for is_unique_key
                $isUniqueKey = false;
                if (isset($mapping['is_unique_key'])) {
                    $value = $mapping['is_unique_key'];
                    $isUniqueKey = ($value === '1' || $value === 1 || $value === 'true' || $value === true);
                }
                
                MappingColumn::create([
                    'mapping_index_id' => $mappingIndex->id,
                    'excel_column_index' => strtoupper($mapping['excel_column']),
                    'table_column_name' => $mapping['database_column'],
                    'data_type' => 'string',
                    'is_required' => false,
                    'is_unique_key' => $isUniqueKey,
                ]);
                
                if ($isUniqueKey) {
                    Log::info("Kolom '{$mapping['database_column']}' ditandai sebagai kunci unik.");
                }
            }
            Log::info("Pemetaan kolom berhasil disimpan.");

            DB::commit();

            // Log activity: create format
            try {
                \App\Models\UploadLog::create([
                    'user_id' => Auth::id(),
                    'division_id' => Auth::user()->division_id,
                    'mapping_index_id' => $mappingIndex->id,
                    'file_name' => $validated['name'],
                    'rows_imported' => 0,
                    'status' => 'success',
                    'action' => 'create_format',
                    'upload_mode' => null,
                    'error_message' => null,
                ]);
            } catch (\Throwable $logException) {
                Log::warning('Gagal mencatat log pembuatan format: ' . $logException->getMessage());
            }

            return redirect()->route('dashboard')->with('success', "Format '{$validated['name']}' berhasil disimpan dan tabel '{$tableName}' telah dibuat dengan dukungan period_date!");
        } catch (\Exception $e) {
            DB::rollBack();
            Schema::dropIfExists($tableName);
            Log::error('Gagal membuat tabel/format: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display all formats/mappings
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        
        $query = MappingIndex::with('columns', 'division');
        $search = trim((string) $request->input('q', ''));

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where('description', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('table_name', 'like', $like)
                    ->orWhereHas('division', function ($dq) use ($like) {
                        $dq->where('name', 'like', $like);
                    });
            });
        }

        $perPage = 10;

        $mappings = $query->orderBy('description')->paginate($perPage)->withQueryString();
        
        // Get statistics for each mapping
        /** @var UploadIndexService $uploadIndexService */
        $uploadIndexService = app(UploadIndexService::class);
        $mappings->getCollection()->each(function ($mapping) use ($user, $uploadIndexService) {
            $tableName = $mapping->table_name;
            $connection = $mapping->target_connection
                ?? $mapping->connection
                ?? config('database.default');
            $this->ensureLegacyConnectionConfigured($connection);

            // Jika tabel tidak ada di koneksi utama mapping tapi ada di legacy, gunakan legacy
            if (!Schema::connection($connection)->hasTable($tableName) && Schema::connection('sqlsrv_legacy')->hasTable($tableName)) {
                $connection = 'sqlsrv_legacy';
            }

            $mapping->is_legacy_source = ($connection === 'sqlsrv_legacy');
            
            $targetTable = $tableName;
            $activeRun = $uploadIndexService->getActiveRun($mapping->id);
            if ($activeRun && $activeRun->period_date) {
                $candidate = $this->buildStrictVersionTableName($tableName, $activeRun->period_date, (int) $activeRun->upload_index);
                if (Schema::connection($connection)->hasTable($candidate)) {
                    $targetTable = $candidate;
                }
            }

            if (Schema::connection($connection)->hasTable($targetTable)) {
                $query = DB::connection($connection)->table($targetTable);
                $uploadIndexFilter = $activeRun?->upload_index;
                if ($uploadIndexFilter === null && Schema::connection($connection)->hasColumn($tableName, 'upload_index')) {
                    $uploadIndexFilter = $this->getActiveUploadIndexFromLegacy($tableName, $connection);
                }

                if (Schema::connection($connection)->hasColumn($targetTable, 'upload_index') && $uploadIndexFilter !== null) {
                    if ($targetTable === $tableName) {
                        $query->where(function ($q2) use ($uploadIndexFilter) {
                            $q2->where('upload_index', $uploadIndexFilter)
                                ->orWhereNull('upload_index');
                        });
                    } else {
                        $query->where('upload_index', $uploadIndexFilter);
                    }
                }
                $mapping->row_count = $query->count();
            } else {
                $mapping->row_count = 0;
            }
        });
        
        $totalFormats = $mappings->total();
        $idsQuery = (clone $query)->reorder()->select('id'); // remove order by for subquery
        $totalColumns = \App\Models\MappingColumn::whereIn('mapping_index_id', $idsQuery)->count();
        $totalRowsCurrentPage = $mappings->getCollection()->sum('row_count');
        $avgColumns = $totalFormats > 0 ? round($totalColumns / $totalFormats, 1) : 0;

        return view('formats.index', [
            'mappings' => $mappings,
            'totalFormats' => $totalFormats,
            'totalColumns' => $totalColumns,
            'totalRows' => $totalRowsCurrentPage,
            'avgColumns' => $avgColumns,
            'search' => $search,
        ]);
    }

    /**
     * Form edit format (tambah kolom / ubah header row).
     */
    public function edit(MappingIndex $mapping): View
    {
        $mapping->load('columns');

        return view('formats.edit', [
            'mapping' => $mapping,
            'columns' => $mapping->columns
                ->sortBy(fn($col) => $this->columnLetterToIndex($col->excel_column_index))
                ->values(),
        ]);
    }

    /**
     * Update format: tambah kolom baru + update header row.
     */
    public function update(Request $request, MappingIndex $mapping): RedirectResponse
    {
        $mapping->load('columns');

        $normalizedColumns = collect($request->input('columns', []))
            ->map(function ($col) {
                return [
                    'database_column' => strtolower(trim($col['database_column'] ?? '')),
                    'is_unique_key' => $col['is_unique_key'] ?? null,
                ];
            })
            ->filter(fn($col) => $col['database_column'] !== '')
            ->values();

        $request->merge(['columns' => $normalizedColumns->all()]);

        $validated = $request->validate([
            'header_row' => ['required', 'integer', 'min:1'],
            'columns' => ['nullable', 'array'],
            'columns.*.database_column' => ['required_with:columns', 'string', 'regex:/^[a-z0-9_]+$/'],
            'columns.*.is_unique_key' => ['nullable', 'in:0,1,true,false'],
        ], [
            'columns.*.database_column.regex' => 'Nama kolom hanya boleh berisi huruf kecil, angka, dan underscore (_).',
        ]);

        $existingDb = $mapping->columns->pluck('table_column_name')->map(fn($v) => strtolower($v));

        // Cek duplikasi di input baru
        $newDb = $normalizedColumns->pluck('database_column');
        if ($newDb->duplicates()->isNotEmpty()) {
            return back()->withInput()->with('error', 'Kolom Database yang baru tidak boleh duplikat.');
        }
        if ($newDb->intersect($existingDb)->isNotEmpty()) {
            return back()->withInput()->with('error', 'Kolom baru bertabrakan dengan kolom yang sudah ada.');
        }

        $reserved = ['id', 'period_date', 'upload_index', 'created_at', 'updated_at', 'is_active', 'division_id'];
        if ($newDb->intersect($reserved)->isNotEmpty()) {
            return back()->withInput()->with('error', 'Nama kolom yang dipilih termasuk kolom terlarang.');
        }

        $connection = $mapping->target_connection
            ?? $mapping->connection
            ?? config('database.default');
        $baseTable = $mapping->table_name;
        $this->ensureLegacyConnectionConfigured($connection);
        if (!Schema::connection($connection)->hasTable($baseTable) && Schema::connection('sqlsrv_legacy')->hasTable($baseTable)) {
            $connection = 'sqlsrv_legacy';
        }

        DB::beginTransaction();
        try {
            // Update header row
            if ((int) $mapping->header_row !== (int) $validated['header_row']) {
                $mapping->header_row = (int) $validated['header_row'];
                $mapping->save();
            }

            // Tambah kolom baru (DB + mapping)
            $maxExistingIndex = $mapping->columns
                ->map(fn($col) => $this->columnLetterToIndex($col->excel_column_index))
                ->max();
            $nextIndex = is_null($maxExistingIndex) ? -1 : (int) $maxExistingIndex;

            foreach ($normalizedColumns as $col) {
                $isUnique = in_array($col['is_unique_key'], ['1', 1, true, 'true'], true);
                $this->addColumnToAllTables($baseTable, $connection, $col['database_column'], $isUnique);

                $nextIndex++;
                $excelCol = $this->indexToColumn($nextIndex);

                MappingColumn::create([
                    'mapping_index_id' => $mapping->id,
                    'excel_column_index' => $excelCol,
                    'table_column_name' => $col['database_column'],
                    'data_type' => 'string',
                    'is_required' => false,
                    'is_unique_key' => $isUnique,
                ]);
            }

            DB::commit();

            return redirect()
                ->route('mapping.edit', $mapping->id)
                ->with('success', 'Format berhasil diperbarui. Kolom baru siap dipakai untuk upload berikutnya.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Gagal mengupdate format', [
                'mapping_id' => $mapping->id,
                'error' => $e->getMessage(),
            ]);
            return back()->withInput()->with('error', 'Gagal memperbarui format: ' . $e->getMessage());
        }
    }

    /**
     * View data from a specific format/mapping table
     */
    public function viewData($mappingId): View|RedirectResponse
    {
        $user = Auth::user();
        $mapping = MappingIndex::with('columns')->findOrFail($mappingId);
        $periodFilter = request()->query('period_date');

        // Pilih koneksi DB sesuai mapping (fallback ke default/legacy)
        $connection = $mapping->target_connection
            ?? $mapping->connection
            ?? config('database.default');
        $baseTableName = $mapping->table_name;
        $this->ensureLegacyConnectionConfigured($connection);

        // Jika tabel tidak ada di koneksi terpilih tapi ada di legacy, pakai legacy
        if (!Schema::connection($connection)->hasTable($baseTableName) && Schema::connection('sqlsrv_legacy')->hasTable($baseTableName)) {
            $connection = 'sqlsrv_legacy';
        }

        /** @var UploadIndexService $uploadIndexService */
        $uploadIndexService = app(UploadIndexService::class);
        $activeRun = null;
        $activeRuns = collect();
        $tableName = $baseTableName;
        $legacyActiveIndex = null;
        if ($periodFilter) {
            $activeRun = $uploadIndexService->getActiveRun($mapping->id, $periodFilter);
            if (! $activeRun) {
                $legacyActiveIndex = $this->getActiveUploadIndexFromLegacy($baseTableName, $connection, $periodFilter);
            }
        } else {
            $activeRuns = $uploadIndexService->getActiveRuns($mapping->id);
            if ($activeRuns->isEmpty()) {
                $legacyActiveIndex = $this->getActiveUploadIndexFromLegacy($baseTableName, $connection);
            }
        }

        // Get column mapping sorted by Excel column order
        $columnMapping = $mapping->columns
            ->sortBy(fn($col) => $this->columnLetterToIndex($col->excel_column_index))
            ->pluck('table_column_name', 'excel_column_index')
            ->toArray();

        if (empty($columnMapping)) {
            return back()->with('error', 'Tidak ada kolom yang di-mapping untuk format ini.');
        }

        $selectColumns = [];
        $validColumns = [];

        $rowsCollection = collect();
        $usedBaseTable = false;

        // Helper to collect data from a specific table (versioned) with optional upload_index filter
        $collectFromTable = function (string $tblName, ?int $uploadIndex = null) use ($connection, $user, &$validColumns, &$selectColumns, $columnMapping, $baseTableName, &$usedBaseTable, $periodFilter) {
            if (!Schema::connection($connection)->hasTable($tblName)) {
                return collect();
            }
            $actualTableColumns = Schema::connection($connection)->getColumnListing($tblName);
            $valid = array_intersect(array_values($columnMapping), $actualTableColumns);
            if (empty($valid)) {
                return collect();
            }
            // Cache once
            if (empty($selectColumns) || empty($validColumns)) {
                $validColumns = $valid;
                $selectColumns = array_values(array_unique(array_filter(array_merge(['id'], $valid, ['period', 'period_date', 'created_at', 'updated_at']), function ($col) use ($actualTableColumns) {
                    return in_array($col, $actualTableColumns, true);
                })));
            }

            $q = DB::connection($connection)->table($tblName)->select($selectColumns);
            if ($periodFilter) {
                $periodColumn = null;
                foreach (['period_date', 'period', 'periode', 'period_dt', 'perioddate'] as $candidate) {
                    if (in_array($candidate, $actualTableColumns, true)) {
                        $periodColumn = $candidate;
                        break;
                    }
                }
                if ($periodColumn) {
                    $q->whereDate($periodColumn, $periodFilter);
                }
            }
            if ($uploadIndex !== null && in_array('upload_index', $actualTableColumns, true)) {
                if ($tblName === $baseTableName) {
                    $q->where(function ($q2) use ($uploadIndex) {
                        $q2->where('upload_index', $uploadIndex)
                            ->orWhereNull('upload_index');
                    });
                } else {
                    $q->where('upload_index', $uploadIndex);
                }
            }
            if (!$this->userHasRole($user, 'superuser') && in_array('division_id', $actualTableColumns)) {
                $q->where('division_id', $user->division_id);
            }
            if ($tblName === $baseTableName) {
                $usedBaseTable = true;
            }
            return $q->get();
        };
        $collectUnversionedBase = function () use ($connection, $user, &$validColumns, &$selectColumns, $columnMapping, $baseTableName, $periodFilter) {
            if (!Schema::connection($connection)->hasTable($baseTableName)) {
                return collect();
            }
            $actualTableColumns = Schema::connection($connection)->getColumnListing($baseTableName);
            $valid = array_intersect(array_values($columnMapping), $actualTableColumns);
            if (empty($valid)) {
                return collect();
            }
            if (empty($selectColumns) || empty($validColumns)) {
                $validColumns = $valid;
                $selectColumns = array_values(array_unique(array_filter(array_merge(['id'], $valid, ['period', 'period_date', 'created_at', 'updated_at']), function ($col) use ($actualTableColumns) {
                    return in_array($col, $actualTableColumns, true);
                })));
            }

            $baseSelectColumns = array_values(array_filter($selectColumns, function ($col) use ($actualTableColumns) {
                return in_array($col, $actualTableColumns, true);
            }));
            if (empty($baseSelectColumns)) {
                return collect();
            }

            $q = DB::connection($connection)->table($baseTableName)->select($baseSelectColumns);
            if (in_array('upload_index', $actualTableColumns, true)) {
                $q->whereNull('upload_index');
            }
            if ($periodFilter) {
                $periodColumn = null;
                foreach (['period_date', 'period', 'periode', 'period_dt', 'perioddate'] as $candidate) {
                    if (in_array($candidate, $actualTableColumns, true)) {
                        $periodColumn = $candidate;
                        break;
                    }
                }
                if ($periodColumn) {
                    $q->whereDate($periodColumn, $periodFilter);
                }
            }
            if (!$this->userHasRole($user, 'superuser') && in_array('division_id', $actualTableColumns)) {
                $q->where('division_id', $user->division_id);
            }
            return $q->get();
        };

        if ($periodFilter) {
            if ($activeRun && $activeRun->period_date) {
                $candidate = $this->buildStrictVersionTableName($baseTableName, $activeRun->period_date, (int) $activeRun->upload_index);
                if (Schema::connection($connection)->hasTable($candidate)) {
                    $rowsCollection = $collectFromTable($candidate, (int) $activeRun->upload_index);
                    $tableName = $candidate;
                } else {
                    $uploadIndexFilter = $activeRun->upload_index ?? $legacyActiveIndex;
                    $rowsCollection = $collectFromTable($baseTableName, $uploadIndexFilter);
                    $tableName = $baseTableName;
                }
            } elseif ($legacyActiveIndex !== null) {
                $rowsCollection = $collectFromTable($baseTableName, $legacyActiveIndex);
                $tableName = $baseTableName;
            } else {
                $rowsCollection = $collectFromTable($baseTableName, null);
                $tableName = $baseTableName;
            }
        } else {
            // No filter: show all active periods (latest per period)
            if ($activeRuns->isEmpty()) {
                $rowsCollection = $collectFromTable($baseTableName, $legacyActiveIndex);
            } else {
                foreach ($activeRuns as $run) {
                    if (!$run->period_date) {
                        continue;
                    }
                    $candidate = $this->buildStrictVersionTableName($baseTableName, $run->period_date, (int) $run->upload_index);
                    if (Schema::connection($connection)->hasTable($candidate)) {
                        $rowsCollection = $rowsCollection->merge(
                            $collectFromTable($candidate, (int) $run->upload_index)
                        );
                    } else {
                        $rowsCollection = $rowsCollection->merge(
                            $collectFromTable($baseTableName, (int) $run->upload_index)
                        );
                    }
                }
                if ($rowsCollection->isEmpty() && Schema::connection($connection)->hasTable($baseTableName)) {
                    $rowsCollection = $collectFromTable($baseTableName, $legacyActiveIndex);
                }
            }
        }
        if (! $periodFilter && ! $usedBaseTable) {
            $unversionedRows = $collectUnversionedBase();
            if ($unversionedRows->isNotEmpty()) {
                $rowsCollection = $rowsCollection->merge($unversionedRows);
            }
        }

        // If still empty and table missing, return error
        if ($rowsCollection === null) {
            return back()->with('error', "Tabel untuk period terpilih tidak ditemukan di koneksi {$connection}.");
        }

        // Manual pagination for merged collections
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 50;
        $total = $rowsCollection->count();
        $items = $rowsCollection->values()->forPage($page, $perPage);
        $data = new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);

        return view('view_data', [
            'mapping' => $mapping,
            'columns' => $validColumns ?: array_values($columnMapping),
            'data' => $data,
            'columnMapping' => $columnMapping,
            'period_date' => $activeRun->period_date ?? $periodFilter,
        ]);
    }

    /**
     * Trim whitespace on selected columns for existing data.
     */
    public function cleanData(Request $request, MappingIndex $mapping): JsonResponse
    {
        $validated = $request->validate([
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['string'],
            'period_date' => ['nullable', 'date'],
        ]);

        $connection = $mapping->target_connection
            ?? $mapping->connection
            ?? config('database.default');
        $baseTableName = $mapping->table_name;
        $this->ensureLegacyConnectionConfigured($connection);

        if (!Schema::connection($connection)->hasTable($baseTableName) && Schema::connection('sqlsrv_legacy')->hasTable($baseTableName)) {
            $connection = 'sqlsrv_legacy';
        }

        $periodFilter = $validated['period_date'] ?? null;
        /** @var UploadIndexService $uploadIndexService */
        $uploadIndexService = app(UploadIndexService::class);
        $activeRun = $uploadIndexService->getActiveRun($mapping->id, $periodFilter);

        $tableName = $baseTableName;
        if ($activeRun && $activeRun->period_date) {
            $candidate = $this->buildStrictVersionTableName($baseTableName, $activeRun->period_date, (int) $activeRun->upload_index);
            if (Schema::connection($connection)->hasTable($candidate)) {
                $tableName = $candidate;
            }
        }

        if (!Schema::connection($connection)->hasTable($tableName)) {
            return response()->json(['success' => false, 'message' => "Tabel '{$tableName}' tidak ditemukan."], 404);
        }

        $actualColumns = Schema::connection($connection)->getColumnListing($tableName);
        $selectedColumns = [];
        foreach ($validated['columns'] as $col) {
            foreach ($actualColumns as $actual) {
                if (strcasecmp($col, $actual) === 0) {
                    $selectedColumns[] = $actual;
                    break;
                }
            }
        }

        if (empty($selectedColumns)) {
            return response()->json(['success' => false, 'message' => 'Kolom yang dipilih tidak ditemukan di tabel.'], 422);
        }

        $connectionInstance = DB::connection($connection);
        $driver = $connectionInstance->getDriverName();
        $tableHasUploadIndex = Schema::connection($connection)->hasColumn($tableName, 'upload_index');
        $legacyActiveIndex = null;
        if ($tableHasUploadIndex && ! $activeRun) {
            $legacyActiveIndex = $this->getActiveUploadIndexFromLegacy($baseTableName, $connection, $periodFilter);
        }

        // Only operate on string-like columns to avoid corrupting dates/numerics
        $stringColumns = [];
        foreach ($selectedColumns as $col) {
            try {
                $colType = Schema::connection($connection)->getColumnType($tableName, $col);
            } catch (\Throwable $e) {
                continue;
            }
            if (in_array(strtolower($colType), ['string', 'text', 'mediumtext', 'longtext', 'char', 'varchar', 'nvarchar', 'nchar'], true)) {
                $stringColumns[] = $col;
            }
        }

        if (empty($stringColumns)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada kolom teks yang bisa dibersihkan.'], 422);
        }

        $connectionInstance->transaction(function () use ($connectionInstance, $driver, $tableName, $stringColumns, $tableHasUploadIndex, $activeRun, $mapping, $periodFilter, $legacyActiveIndex) {
            foreach ($stringColumns as $col) {
                $wrappedCol = $connectionInstance->getQueryGrammar()->wrap($col);

                if ($driver === 'sqlsrv') {
                    // Remove all whitespace characters (space, tab, CR/LF, NBSP) anywhere in the string
                    $cleanExpr = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$wrappedCol}, CHAR(160), ''), CHAR(9), ''), CHAR(10), ''), CHAR(13), ''), ' ', '')";
                    $trimExpr = DB::raw($cleanExpr);
                } else {
                    // Remove all whitespace characters (space, tab, CR/LF, NBSP) anywhere in the string
                    $cleanExpr = "REPLACE(REPLACE(REPLACE(REPLACE({$wrappedCol}, '\\t', ''), '\\r', ''), '\\n', ''), CHAR(160), '')";
                    $cleanExpr = "REPLACE({$cleanExpr}, ' ', '')";
                    $trimExpr = DB::raw($cleanExpr);
                }

                $query = $connectionInstance->table($tableName)->whereNotNull($col);
                if ($tableHasUploadIndex) {
                    $uploadIndexFilter = $activeRun->upload_index ?? $legacyActiveIndex;
                    if ($uploadIndexFilter !== null) {
                        $query->where('upload_index', $uploadIndexFilter);
                    }
                }
                $query->update([$col => $trimExpr]);
            }

            if (Schema::hasTable('upload_logs')) {
                DB::table('upload_logs')->insert([
                    'user_id' => Auth::id(),
                    'division_id' => Auth::user()?->division_id,
                    'mapping_index_id' => $mapping->id,
                    'file_name' => 'manual-clean',
                    'rows_imported' => 0,
                    'status' => 'success',
                    'action' => 'clean_trim',
                    'upload_mode' => null,
                    'error_message' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Spasi berhasil dibersihkan untuk kolom: ' . implode(', ', $selectedColumns),
        ]);
    }

    /**
     * Extract headers from uploaded Excel (for register form).
     */
    public function extractHeaders(Request $request): JsonResponse
    {
        try {
            @set_time_limit(120);
            Log::info('Extract headers request received', [
                'header_row' => $request->input('header_row'),
                'file' => $request->file('data_file')?->getClientOriginalName(),
                'size' => $request->file('data_file')?->getSize(),
            ]);

            $validated = $request->validate([
                'data_file' => ['required', File::types(['xlsx', 'xls'])],
                'header_row' => ['required', 'integer', 'min:1'],
            ]);

            $headerRowIndex = max(0, $validated['header_row'] - 1);

            $headers = $this->loadHeaderRow($request->file('data_file'), $headerRowIndex);

            // Filter header kosong di ujung
            $headers = collect($headers)
                ->filter(fn($item) => ($item['header'] ?? '') !== '')
                ->values()
                ->all();

            if (empty($headers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Header tidak ditemukan pada baris tersebut.'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'sheet_name' => 'sheet_1',
                'headers' => $headers,
            ]);
        } catch (\Throwable $e) {
            Log::error('Gagal extract headers: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $request->file('data_file')?->getClientOriginalName(),
            ]);
            $message = $e instanceof \Illuminate\Validation\ValidationException
                ? implode(' ', collect($e->errors())->flatten()->all())
                : 'Gagal memproses file. Pastikan format dan ukuran sesuai.';
            return response()->json([
                'success' => false,
                'message' => $message,
            ], $e instanceof \Illuminate\Validation\ValidationException ? 422 : 500);
        }
    }

    /**
     * Preview upload - Menampilkan preview data dan mapping
     */
    public function showUploadPreview(Request $request): JsonResponse
    {
        try {
            @set_time_limit(300);
            $validated = $request->validate([
                'data_file' => ['required', File::types(['xlsx', 'xls'])],
                'mapping_id' => ['required', 'integer', Rule::exists('mapping_indices', 'id')],
                'sheet_name' => ['nullable', 'string'],
            ]);

            $mapping = MappingIndex::with('columns')->find($validated['mapping_id']);
            
            if (!$mapping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format tidak ditemukan.'
                ]);
            }

            $headerRowIndex = max(0, $mapping->header_row - 1);

            // Baca semua sheet (hanya header dan beberapa baris) untuk preview
            $sheets = $this->loadSheetsPreview($request->file('data_file'), $headerRowIndex, 5);

            if (empty($sheets)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File Excel tidak memiliki sheet yang bisa dibaca.'
                ]);
            }

            $expectedHeaders = $this->buildExpectedHeaders($mapping->columns);
            $selection = $this->determineSheetSelection(
                $sheets,
                $expectedHeaders,
                $headerRowIndex,
                $validated['sheet_name'] ?? null
            );

            if ($selection['sheet']['rows']->count() <= $headerRowIndex) {
                return response()->json([
                    'success' => false,
                    'message' => "Sheet '{$selection['sheet_name']}' tidak memiliki baris header ke-{$mapping->header_row}."
                ]);
            }

            $headers = $selection['sheet']['rows']->get($headerRowIndex, collect());
            if (!$headers instanceof \Illuminate\Support\Collection) {
                $headers = collect($headers ?? []);
            }

            $previewRows = $selection['sheet']['rows']->slice($headerRowIndex + 1, 5);

            // Generate HTML
            $html = $this->generatePreviewHtml($mapping, $headers, $previewRows, $mapping->columns);

            return response()->json([
                'success' => true,
                'html' => $html,
                'sheets' => $selection['matches'],
                'selected_sheet' => $selection['sheet_name'],
                'auto_selected' => $selection['auto_selected'],
            ]);

        } catch (\Exception $e) {
            Log::error('Preview error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Generate HTML for interactive preview with checkboxes, dropdowns, and upload mode
     */
    private function generatePreviewHtml($mapping, $headers, $previewRows, $mappingRules): string
    {
        $html = '<div class="space-y-6">';
        
        // Info section
        $html .= '<div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded">';
        $html .= '<div class="flex items-start">';
        $html .= '<svg class="w-5 h-5 text-blue-400 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>';
        $html .= '<div class="flex-1">';
        $html .= '<p class="text-sm font-medium text-blue-800">Informasi Format</p>';
        $html .= '<div class="mt-2 text-sm text-blue-700">';
        $html .= '<p><strong>Format:</strong> ' . htmlspecialchars($mapping->description) . '</p>';
        $html .= '<p><strong>Tabel Tujuan:</strong> ' . htmlspecialchars($mapping->table_name) . '</p>';
        $html .= '<p><strong>Baris Header:</strong> ' . $mapping->header_row . '</p>';
        $html .= '</div></div></div></div>';
        // Upload Mode Selection
        $html .= '<div class="border rounded-lg overflow-hidden bg-gradient-to-br from-amber-50 to-orange-50 border-amber-200">';
        $html .= '<div class="bg-gradient-to-r from-amber-500 to-orange-500 px-4 py-3 border-b">';
        $html .= '<h4 class="font-medium text-white flex items-center">';
        $html .= '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>';
        $html .= 'Mode Upload';
        $html .= '</h4>';
        $html .= '</div>';
        $html .= '<div class="p-4">';
        if ($mapping->upload_mode) {
            $lockedMode = $mapping->upload_mode;
            $lockedLabel = $lockedMode === 'strict'
                ? 'Strict (Replace by Period)'
                : 'Upsert (Update atau Insert)';
            $lockedDesc = $lockedMode === 'strict'
                ? 'Sistem akan mengganti data per periode berdasarkan tanggal hasil mapping.'
                : 'Update data yang sudah ada berdasarkan kunci unik, atau insert data baru jika belum ada.';

            $html .= '<div class="flex items-start p-3 bg-white rounded-lg border-2 border-dashed border-amber-300">';
            $html .= '<div class="ml-3">';
            $html .= '<div class="flex items-center">';
            $html .= '<span class="font-semibold text-gray-900">' . $lockedLabel . '</span>';
            $html .= '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">Terkunci</span>';
            $html .= '</div>';
            $html .= '<p class="text-sm text-gray-600 mt-1">' . $lockedDesc . '</p>';
            if ($lockedMode === 'strict') {
                $html .= '<p class="text-xs text-red-600 mt-1 font-medium">Perhatian: pastikan kolom tanggal di Excel sudah dimapping ke \\\'period_date\\\'!</p>';
            }
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<input type="hidden" name="upload_mode" value="' . htmlspecialchars($lockedMode, ENT_QUOTES, 'UTF-8') . '">';
        } else {
            $html .= '<div class="space-y-3">';
            
            // Upsert mode
            $html .= '<label class="flex items-start p-3 bg-white rounded-lg border-2 border-green-200 cursor-pointer hover:border-green-400 transition-all duration-200">';
            $html .= '<input type="radio" name="upload_mode" value="upsert" checked class="mt-1 rounded-full border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">';
            $html .= '<div class="ml-3">';
            $html .= '<div class="flex items-center">';
            $html .= '<span class="font-semibold text-gray-900">Upsert (Update atau Insert)</span>';
            $html .= '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Rekomendasi</span>';
            $html .= '</div>';
            $html .= '<p class="text-sm text-gray-600 mt-1">Update data yang sudah ada berdasarkan kunci unik, atau insert data baru jika belum ada</p>';
            $html .= '</div>';
            $html .= '</label>';
            
            // Strict mode
            $html .= '<label class="flex items-start p-3 bg-white rounded-lg border-2 border-red-200 cursor-pointer hover:border-red-400 transition-all duration-200">';
            $html .= '<input type="radio" name="upload_mode" value="strict" class="mt-1 rounded-full border-gray-300 text-red-600 shadow-sm focus:border-red-300 focus:ring focus:ring-red-200 focus:ring-opacity-50">';
            $html .= '<div class="ml-3">';
            $html .= '<div class="flex items-center">';
            $html .= '<span class="font-semibold text-gray-900">Strict (Replace by Period)</span>';
            $html .= '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Hati-hati</span>';
            $html .= '</div>';
            $html .= '<p class="text-sm text-gray-600 mt-1">Sistem akan <strong>otomatis mendeteksi tanggal</strong> dari file Excel (berdasarkan mapping), menghapus data lama pada bulan tersebut, lalu memasukkan data baru.</p>';
            $html .= '<p class="text-xs text-red-600 mt-1 font-medium">Perhatian: pastikan kolom tanggal di Excel sudah dimapping ke "period" </p>';
            $html .= '</div>';
            $html .= '</label>';
            
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
        
        // Mapping Configuration section
        $html .= '<div class="border rounded-lg overflow-hidden">';
        $html .= '<div class="bg-gray-50 px-4 py-3 border-b flex items-center justify-between">';
        $html .= '<h4 class="font-medium text-gray-900">Konfigurasi Mapping Kolom</h4>';
        $html .= '<label class="inline-flex items-center cursor-pointer">';
        $html .= '<input type="checkbox" id="selectAllColumns" checked class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">';
        $html .= '<span class="ml-2 text-sm text-gray-700">Pilih Semua</span>';
        $html .= '</label></div>';
        
        $html .= '<div class="divide-y divide-gray-200">';
        
        // Get available database columns for mapping
        $dbColumns = $mappingRules->pluck('table_column_name', 'excel_column_index')->toArray();
        $allDbColumns = $mappingRules->pluck('table_column_name')->toArray();
        
        foreach ($headers as $index => $header) {
            $excelCol = $this->indexToColumn($index);
            $mappedDbCol = $dbColumns[$excelCol] ?? '';
            
            $html .= '<div class="p-4 hover:bg-gray-50 transition">';
            $html .= '<div class="flex items-start space-x-4">';
            
            // Checkbox
            $html .= '<div class="flex items-center h-10">';
            $html .= '<input type="checkbox" checked class="column-checkbox rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" data-excel-col="' . $excelCol . '">';
            $html .= '</div>';
            
            // Excel column info
            $html .= '<div class="flex-1">';
            $html .= '<div class="flex items-center space-x-2 mb-2">';
            $html .= '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Excel: ' . $excelCol . '</span>';
            $html .= '<span class="text-sm font-medium text-gray-900">' . htmlspecialchars($header) . '</span>';
            $html .= '</div>';
            
            // Mapping dropdown
            $html .= '<div class="flex items-center space-x-2">';
            $html .= '<span class="text-sm text-gray-500">→</span>';
            $html .= '<select id="mapping_' . $excelCol . '" class="text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">';
            $html .= '<option value="">-- Tidak Diimport --</option>';
            foreach ($allDbColumns as $dbCol) {
                $selected = ($dbCol === $mappedDbCol) ? 'selected' : '';
                $html .= '<option value="' . htmlspecialchars($dbCol) . '" ' . $selected . '>' . htmlspecialchars($dbCol) . '</option>';
            }
            $html .= '</select>';
            $html .= '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">DB: ' . ($mappedDbCol ?: 'N/A') . '</span>';
            $html .= '</div>';
            
            // Sample data
            $sampleData = [];
            foreach ($previewRows as $row) {
                if (isset($row[$index]) && $row[$index] != '') {
                    $sampleData[] = $row[$index];
                }
                if (count($sampleData) >= 2) break;
            }
            
            if (!empty($sampleData)) {
                $html .= '<div class="mt-2 text-xs text-gray-500">';
                $html .= '<span class="font-medium">Contoh data:</span> ';
                $html .= htmlspecialchars(implode(', ', $sampleData));
                if (count($previewRows) > 2) {
                    $html .= ', ...';
                }
                $html .= '</div>';
            }
            
            $html .= '</div></div></div>';
        }
        
        $html .= '</div></div>';
        
        // Preview data table
        $html .= '<div class="border rounded-lg overflow-hidden">';
        $html .= '<div class="bg-gray-50 px-4 py-3 border-b">';
        $html .= '<h4 class="font-medium text-gray-900">Preview Data (5 baris pertama)</h4>';
        $html .= '</div>';
        $html .= '<div class="overflow-x-auto max-h-80">';
        $html .= '<table class="min-w-full divide-y divide-gray-200 text-sm">';
        
        // Table header
        $html .= '<thead class="bg-gray-50 sticky top-0">';
        $html .= '<tr>';
        $html .= '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-100">#</th>';
        foreach ($headers as $index => $header) {
            $excelCol = $this->indexToColumn($index);
            $html .= '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">';
            $html .= '<div class="flex flex-col">';
            $html .= '<span class="text-yellow-600">' . $excelCol . '</span>';
            $html .= '<span class="font-normal normal-case text-gray-700">' . htmlspecialchars($header) . '</span>';
            $html .= '</div>';
            $html .= '</th>';
        }
        $html .= '</tr></thead>';
        
        // Table body
        $html .= '<tbody class="bg-white divide-y divide-gray-200">';
        $rowNum = 1;
        foreach ($previewRows as $row) {
            $html .= '<tr class="hover:bg-gray-50">';
            $html .= '<td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500 font-medium bg-gray-50">' . $rowNum . '</td>';
            foreach ($row as $cell) {
                $html .= '<td class="px-3 py-2 whitespace-nowrap text-sm">' . htmlspecialchars($cell ?? '') . '</td>';
            }
            $html .= '</tr>';
            $rowNum++;
        }
        $html .= '</tbody>';
        
        $html .= '</table>';
        $html .= '</div>';
        $html .= '<div class="bg-gray-50 px-4 py-2 border-t">';
        $html .= '<p class="text-xs text-gray-500">Menampilkan 5 dari total ' . ($previewRows->count() + $mapping->header_row) . '+ baris</p>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Build normalized expected headers from mapping rules.
     */
    private function buildExpectedHeaders($mappingRules): array
    {
        return $mappingRules
            ->pluck('table_column_name')
            ->map(fn($value) => $this->normalizeHeaderValue($value))
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Load all sheets from the uploaded Excel file as collections.
     */
    private function loadSheets($file): array
    {
        $reader = IOFactory::createReaderForFile($file->getPathname());
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($file->getPathname());
        $sheets = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $rows = collect($sheet->toArray(null, true, true, false))
                ->map(fn($row) => collect($row));

            $sheets[] = [
                'name' => $sheet->getTitle(),
                'rows' => $rows,
            ];
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $sheets;
    }

    /**
     * Load only header + limited rows per sheet to reduce memory (for preview).
     */
    private function loadSheetsPreview($file, int $headerRowIndex, int $previewRows = 5): array
    {
        $path = $file->getPathname();
        /** @var \PhpOffice\PhpSpreadsheet\Reader\IReader $reader */
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        // Target rows (1-based) to read
        $startRow = $headerRowIndex + 1;
        $endRow = $startRow + $previewRows;

        $sheetNames = $this->listWorksheetNames($file);
        $result = [];

        foreach ($sheetNames as $sheetName) {
            // Limit to single sheet per load
            if (method_exists($reader, 'setLoadSheetsOnly')) {
                $reader->setLoadSheetsOnly([$sheetName]);
            }

            // Filter only needed rows
            $filter = new class($startRow, $endRow) implements IReadFilter {
                public function __construct(private int $start, private int $end) {}
                public function readCell($column, $row, $worksheetName = ''): bool
                {
                    return $row >= $this->start && $row <= $this->end;
                }
            };

            if (method_exists($reader, 'setReadFilter')) {
                $reader->setReadFilter($filter);
            }

            $spreadsheet = $reader->load($path);
            $worksheet = $spreadsheet->getSheet(0);

            $highestColumn = $worksheet->getHighestColumn();
            $range = "A{$startRow}:{$highestColumn}{$endRow}";
            $rowsArray = $worksheet->rangeToArray($range, null, true, true, false);

            $rows = collect($rowsArray)->map(fn($row) => collect($row));

            $result[] = [
                'name' => $sheetName,
                'rows' => $rows,
            ];

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }

        return $result;
    }

    /**
     * Get worksheet names safely.
     */
    private function listWorksheetNames($file): array
    {
        $path = $file->getPathname();
        /** @var \PhpOffice\PhpSpreadsheet\Reader\IReader $reader */
        $reader = IOFactory::createReaderForFile($path);
        return method_exists($reader, 'listWorksheetNames')
            ? $reader->listWorksheetNames($path)
            : ['Sheet1'];
    }

    /**
     * Get total rows of a worksheet if available, fallback to a large number.
     */
    private function getWorksheetRowCount($file, string $sheetName): int
    {
        $path = $file->getPathname();
        /** @var \PhpOffice\PhpSpreadsheet\Reader\IReader $reader */
        $reader = IOFactory::createReaderForFile($path);

        if (method_exists($reader, 'listWorksheetInfo')) {
            $info = $reader->listWorksheetInfo($path);
            foreach ($info as $sheetInfo) {
                if (($sheetInfo['worksheetName'] ?? '') === $sheetName) {
                    return (int) ($sheetInfo['totalRows'] ?? 0);
                }
            }
        }

        // Fallback: assume very large, loop stops when chunk has no data
        return 1000000;
    }

    /**
     * Drop staging table safely on specific connection and also attempt on legacy/default to ensure cleanup.
     */
    private function dropStagingTable(?string $tableName, string $primaryConnection, bool $isCleanup = false): void
    {
        if (! $tableName) {
            return;
        }

        $connections = array_unique([
            $primaryConnection,
            'sqlsrv',
            'sqlsrv_legacy',
        ]);

        foreach ($connections as $conn) {
            try {
                if (Schema::connection($conn)->hasTable($tableName)) {
                    Log::info(($isCleanup ? 'Cleanup: ' : '') . "Dropping staging table '{$tableName}' on connection '{$conn}'");
                    Schema::connection($conn)->dropIfExists($tableName);
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to drop staging table '{$tableName}' on connection '{$conn}': " . $e->getMessage());
            }
        }
    }

    /**
     * Generator to stream rows from a specific sheet starting at given row.
     */
    private function sheetRowGenerator($file, string $sheetName, int $startRow): \Generator
    {
        $path = $file->getPathname();
        /** @var \PhpOffice\PhpSpreadsheet\Reader\IReader $reader */
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        if (method_exists($reader, 'setLoadSheetsOnly')) {
            $reader->setLoadSheetsOnly([$sheetName]);
        }

        $spreadsheet = $reader->load($path);
        $worksheet = $spreadsheet->getSheetByName($sheetName) ?? $spreadsheet->getSheet(0);

        foreach ($worksheet->getRowIterator($startRow) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            $rowData = [];
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue();
            }
            yield collect($rowData);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * Load only the specified header row from the first sheet to minimize memory.
     */
    private function loadHeaderRow($file, int $headerRowIndex, ?string $sheetName = null): array
    {
        $rowNumber = $headerRowIndex + 1; // Spreadsheet is 1-based
        $reader = IOFactory::createReaderForFile($file->getPathname());
        $reader->setReadDataOnly(true);

        // Restrict reading to the target row only
        $filter = new class($rowNumber) implements IReadFilter {
            public function __construct(private int $rowNumber) {}
            public function readCell($column, $row, $worksheetName = ''): bool
            {
                return $row === $this->rowNumber;
            }
        };

        if (method_exists($reader, 'setReadFilter')) {
            $reader->setReadFilter($filter);
        }

        if ($sheetName && method_exists($reader, 'setLoadSheetsOnly')) {
            $reader->setLoadSheetsOnly([$sheetName]);
        }

        $spreadsheet = $reader->load($file->getPathname());
        $worksheet = $sheetName
            ? ($spreadsheet->getSheetByName($sheetName) ?? $spreadsheet->getSheet(0))
            : $spreadsheet->getSheet(0);

        $headers = [];
        foreach ($worksheet->getRowIterator($rowNumber, $rowNumber) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            foreach ($cellIterator as $cell) {
                $headers[] = [
                    'excel_column' => strtoupper($cell->getColumn()),
                    'header' => is_scalar($cell->getValue()) ? (string) $cell->getValue() : '',
                ];
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $headers;
    }

    /**
     * Normalize header value for comparison.
     */
    private function normalizeHeaderValue($value): string
    {
        $normalized = str_replace(['_', '-', '.'], ' ', (string) $value);
        $normalized = preg_replace('/\s+/', ' ', $normalized ?? '');
        return strtolower(trim($normalized));
    }

    /**
     * Calculate match score between a sheet header and expected headers.
     */
    private function calculateMatchScore($headerRow, array $expectedHeaders): int
    {
        if (!$headerRow instanceof \Illuminate\Support\Collection) {
            $headerRow = collect($headerRow ?? []);
        }

        $normalizedHeaders = $headerRow
            ->filter(fn($value) => $value !== null && $value !== '')
            ->map(fn($value) => $this->normalizeHeaderValue($value))
            ->toArray();

        return count(array_intersect($normalizedHeaders, $expectedHeaders));
    }

    /**
     * Determine which sheet to use (auto-detect based on header similarity if needed).
     */
    private function determineSheetSelection(array $sheets, array $expectedHeaders, int $headerRowIndex, ?string $requestedSheet = null): array
    {
        $matches = [];
        $bestScore = PHP_INT_MIN;
        $bestSheetName = null;

        foreach ($sheets as $sheet) {
            $score = ($sheet['rows']->count() > $headerRowIndex)
                ? $this->calculateMatchScore($sheet['rows']->get($headerRowIndex, collect()), $expectedHeaders)
                : -1;

            $matches[] = [
                'name' => $sheet['name'],
                'match_score' => $score,
            ];

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestSheetName = $sheet['name'];
            }
        }

        $sheetNames = collect($sheets)->pluck('name')->all();
        $requestedExists = $requestedSheet && in_array($requestedSheet, $sheetNames, true);

        $selectedName = $requestedExists ? $requestedSheet : ($bestSheetName ?? ($sheetNames[0] ?? null));
        $autoSelected = !$requestedExists;

        $selectedSheet = collect($sheets)->firstWhere('name', $selectedName) ?? $sheets[0];

        return [
            'sheet' => $selectedSheet,
            'sheet_name' => $selectedName,
            'auto_selected' => $autoSelected,
            'matches' => $matches,
        ];
    }

    /**
     * Convert column index (0-based) to Excel column letter
     */
    private function indexToColumn(int $index): string
    {
        $column = '';
        while ($index >= 0) {
            $column = chr(65 + ($index % 26)) . $column;
            $index = intval($index / 26) - 1;
        }
        return $column;
    }

    /**
     * Fast batch insert for SQL Server without hitting 2100-parameter limit by inlining values.
     */
    private function insertSqlServerBatch(string $table, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        // SQL Server limits INSERT ... VALUES to 1000 row value expressions
        $rowLimit = 900;
        if (count($rows) > $rowLimit) {
            foreach (array_chunk($rows, $rowLimit) as $chunk) {
                $this->insertSqlServerBatch($table, $chunk);
            }
            return;
        }

        $columns = array_keys($rows[0]);
        $quotedColumns = array_map(fn($col) => '[' . $col . ']', $columns);

        $pdo = DB::connection()->getPdo();
        $valuesSql = [];

        foreach ($rows as $row) {
            $vals = [];
            foreach ($columns as $col) {
                $value = $row[$col] ?? null;
                if ($value === null) {
                    $vals[] = 'NULL';
                    continue;
                }

                // Normalize booleans to tinyint
                if (is_bool($value)) {
                    $value = $value ? 1 : 0;
                }

                // Quote scalar values safely
                $vals[] = $pdo->quote((string) $value);
            }
            $valuesSql[] = '(' . implode(',', $vals) . ')';
        }

        $sql = 'INSERT INTO [' . $table . '] (' . implode(',', $quotedColumns) . ') VALUES ' . implode(',', $valuesSql) . ';';
        DB::unprepared($sql);
    }

    /**
     * Convert Excel column letters (e.g., A, Z, AA) into zero-based numeric index.
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
                // Skip invalid characters so we don't break the calculation mid-way
                continue;
            }
            $index = ($index * 26) + (ord($char) - ord('A') + 1);
        }

        // convert to zero-based index
        return max(0, $index - 1);
    }

    /**
     * Clear only the data rows of a mapping's table.
     */
    public function clearData(MappingIndex $mapping): RedirectResponse
    {
        $user = Auth::user();

        if (!$this->userHasRole($user, 'superuser') && $mapping->division_id !== $user->division_id) {
            return redirect()
                ->route('formats.index')
                ->with('error', 'Anda tidak memiliki akses untuk menghapus isi format ini.');
        }

        $baseTableName = $mapping->table_name;
        $connection = $mapping->target_connection
            ?? $mapping->connection
            ?? config('database.default');

        // Fallback to legacy if not found on chosen connection
        if (!Schema::connection($connection)->hasTable($baseTableName) && Schema::connection('sqlsrv_legacy')->hasTable($baseTableName)) {
            $connection = 'sqlsrv_legacy';
        }

        // Determine active table (handles strict versioned table)
        /** @var UploadIndexService $uploadIndexService */
        $uploadIndexService = app(UploadIndexService::class);
        $activeRun = $uploadIndexService->getActiveRun($mapping->id);
        $tableName = $baseTableName;
        if ($activeRun && $activeRun->period_date) {
            $candidate = $this->buildStrictVersionTableName($baseTableName, $activeRun->period_date, (int) $activeRun->upload_index);
            if (Schema::connection($connection)->hasTable($candidate)) {
                $tableName = $candidate;
            }
        }

        if (!$tableName || !Schema::connection($connection)->hasTable($tableName)) {
            return redirect()->route('formats.index')->with('error', "Tabel '{$tableName}' tidak ditemukan.");
        }

        try {
            $connectionInstance = DB::connection($connection);
            $rowCount = $connectionInstance->table($tableName)->count();

            $driver = $connectionInstance->getDriverName();

            $resetIdentity = function () use ($connectionInstance, $driver, $tableName) {
                try {
                    if ($driver === 'sqlsrv') {
                        $connectionInstance->statement("DBCC CHECKIDENT ('[{$tableName}]', RESEED, 0)");
                    } elseif ($driver === 'pgsql') {
                        $connectionInstance->statement("SELECT setval(pg_get_serial_sequence('{$tableName}', 'id'), 1, false)");
                    } else {
                        $connectionInstance->statement("ALTER TABLE `{$tableName}` AUTO_INCREMENT = 1");
                    }
                } catch (\Throwable $e) {
                    Log::warning("Gagal reset identity untuk tabel {$tableName} (conn: {$connection}): " . $e->getMessage());
                }
            };

            try {
                if ($driver === 'sqlsrv') {
                    $connectionInstance->statement("TRUNCATE TABLE [{$tableName}]");
                } elseif ($driver === 'pgsql') {
                    $connectionInstance->statement("TRUNCATE TABLE \"{$tableName}\" RESTART IDENTITY CASCADE");
                } else {
                    $connectionInstance->statement("TRUNCATE TABLE `{$tableName}`");
                }
            } catch (\Throwable $t) {
                // Fallback: delete all rows then reset identity
                $connectionInstance->table($tableName)->delete();
                $resetIdentity();
            }

            Log::warning("Tabel {$tableName} dikosongkan & ID di-reset ({$rowCount} baris dihapus) oleh user {$user->id}");

            // Log success
            try {
                \App\Models\UploadLog::create([
                    'user_id' => $user->id,
                    'division_id' => $user->division_id,
                    'mapping_index_id' => $mapping->id,
                    'file_name' => $tableName,
                    'rows_imported' => 0,
                    'status' => 'success',
                    'action' => 'clear_data',
                    'upload_mode' => null,
                    'error_message' => null,
                ]);
            } catch (\Throwable $logEx) {
                Log::warning('Gagal mencatat log clear_data: ' . $logEx->getMessage());
            }

            return redirect()
                ->route('formats.index')
                ->with('success', "Isi tabel '{$tableName}' berhasil dihapus dan ID di-reset (total {$rowCount} baris).");
        } catch (\Exception $e) {
            Log::error("Gagal mengosongkan tabel {$tableName}: " . $e->getMessage());

            // Log failure
            try {
                \App\Models\UploadLog::create([
                    'user_id' => $user->id,
                    'division_id' => $user->division_id,
                    'mapping_index_id' => $mapping->id,
                    'file_name' => $tableName,
                    'rows_imported' => 0,
                    'status' => 'failed',
                    'action' => 'clear_data',
                    'upload_mode' => null,
                    'error_message' => $e->getMessage(),
                ]);
            } catch (\Throwable $logEx) {
                Log::warning('Gagal mencatat log clear_data gagal: ' . $logEx->getMessage());
            }

            return redirect()
                ->route('formats.index')
                ->with('error', 'Gagal menghapus isi tabel: ' . $e->getMessage());
        }
    }

    /**
     * Delete mapping format and its table data with confirmation guard.
     */
    public function destroy(MappingIndex $mapping): RedirectResponse
    {
        $user = Auth::user();

        // Only allow same division unless superuser
        if (!$this->userHasRole($user, 'superuser') && $mapping->division_id !== $user->division_id) {
            return redirect()
                ->route('formats.index')
                ->with('error', 'Anda tidak memiliki akses untuk menghapus format ini.');
        }

        $tableName = $mapping->table_name;
        Log::warning("Menghapus format {$mapping->id} ({$mapping->description}) beserta tabel {$tableName}");

        DB::beginTransaction();
        try {
            // Drop data table if exists
            if ($tableName && Schema::hasTable($tableName)) {
                Schema::dropIfExists($tableName);
                Log::info("Tabel {$tableName} dihapus.");
            }

            // Delete mapping columns and index
            $mapping->columns()->delete();
            $mapping->delete();

            DB::commit();
            Log::info("Format {$mapping->id} berhasil dihapus.");

            // Log success delete format
            try {
                \App\Models\UploadLog::create([
                    'user_id' => $user->id,
                    'division_id' => $user->division_id,
                    'mapping_index_id' => $mapping->id,
                    'file_name' => $tableName,
                    'rows_imported' => 0,
                    'status' => 'success',
                    'action' => 'delete_format',
                    'upload_mode' => null,
                    'error_message' => null,
                ]);
            } catch (\Throwable $logEx) {
                Log::warning('Gagal mencatat log delete_format: ' . $logEx->getMessage());
            }

            return redirect()
                ->route('formats.index')
                ->with('success', "Format '{$mapping->description}' dan tabel '{$tableName}' berhasil dihapus.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal menghapus format: ' . $e->getMessage());

            // Log failure delete format
            try {
                \App\Models\UploadLog::create([
                    'user_id' => $user->id,
                    'division_id' => $user->division_id,
                    'mapping_index_id' => $mapping->id,
                    'file_name' => $tableName,
                    'rows_imported' => 0,
                    'status' => 'failed',
                    'action' => 'delete_format',
                    'upload_mode' => null,
                    'error_message' => $e->getMessage(),
                ]);
            } catch (\Throwable $logEx) {
                Log::warning('Gagal mencatat log delete_format gagal: ' . $logEx->getMessage());
            }

            return redirect()
                ->route('formats.index')
                ->with('error', 'Gagal menghapus format: ' . $e->getMessage());
        }
    }

    /**
     * Tambahkan kolom baru ke tabel utama + semua tabel versi strict yang sudah ada.
     */
    private function addColumnToAllTables(string $baseTable, string $connection, string $columnName, bool $isUniqueKey = false): void
    {
        $tables = array_merge([$baseTable], $this->getVersionTables($baseTable, $connection));
        foreach ($tables as $table) {
            $this->addColumnIfMissing($table, $connection, $columnName, $isUniqueKey);
        }
    }

    /**
     * Ambil daftar tabel versi strict (nama mirip base__p...__i...).
     */
    private function getVersionTables(string $baseTable, string $connection): array
    {
        $conn = DB::connection($connection);
        $driver = $conn->getDriverName();
        $likePattern = $baseTable . '__p%__i%';

        try {
            if ($driver === 'sqlsrv') {
                $rows = $conn->select("SELECT name FROM sys.tables WHERE name LIKE ?", [$likePattern]);
                return collect($rows)->pluck('name')->all();
            }
            if ($driver === 'mysql') {
                $rows = $conn->select("SHOW TABLES LIKE ?", [$likePattern]);
                return collect($rows)->map(function ($row) {
                    return array_values((array) $row)[0] ?? null;
                })->filter()->all();
            }
            if ($driver === 'pgsql') {
                $rows = $conn->select("SELECT tablename FROM pg_tables WHERE tablename ILIKE ?", [$likePattern]);
                return collect($rows)->pluck('tablename')->all();
            }
        } catch (\Throwable $e) {
            Log::warning("Gagal mendapatkan daftar tabel versi strict: " . $e->getMessage());
        }

        return [];
    }

    /**
     * Tambah kolom jika belum ada pada tabel tertentu.
     */
    private function addColumnIfMissing(string $tableName, string $connection, string $columnName, bool $isUniqueKey = false): void
    {
        $schema = Schema::connection($connection);
        if (! $schema->hasTable($tableName)) {
            return;
        }
        if ($schema->hasColumn($tableName, $columnName)) {
            return;
        }

        $schema->table($tableName, function (Blueprint $table) use ($columnName, $isUniqueKey) {
            if ($isUniqueKey) {
                $table->string($columnName, 450)->nullable();
            } else {
                $table->text($columnName)->nullable();
            }
        });
    }

    /**
     * Convert Excel date serial number to readable date format
     * Excel stores dates as numbers (days since 1900-01-01)
     * Also handles string dates like DD/MM/YYYY or DD-MM-YYYY
     */
    private function convertExcelDate($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }
        if (is_string($value)) {
            $value = trim($value);
        }

        // 1. If it's already a DateTime instance
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        // 2. Check if already in Database Format (Y-m-d)
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        // 3. Check Numeric (Excel Serial Date)
        if (is_numeric($value) && $value >= 18264 && $value <= 60000) {
            try {
                $unixTimestamp = ($value - 25569) * 86400;
                return \Carbon\Carbon::createFromTimestamp($unixTimestamp)->format('Y-m-d');
            } catch (\Exception $e) {
                // Continue to fallback
            }
        }

        // 4. Try explicit d/m/Y or d-m-Y (common spreadsheet text export)
        if (is_string($value) && preg_match('/^(\\d{1,2})[\\/\\-](\\d{1,2})[\\/\\-](\\d{4})$/', $value, $m)) {
            // Assume day-month-year; if ambiguous (both <=12) still treat first as day to match local usage
            try {
                return \Carbon\Carbon::createFromFormat('d-m-Y', "{$m[1]}-{$m[2]}-{$m[3]}")->format('Y-m-d');
            } catch (\Exception $e) {
                // continue to fallback
            }
        }

        // 5. Fallback: Carbon Parse (Human Readable like '03-Mar-2025')
        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to parse date string: {$value}");
            return $value; // Return original if failed
        }
    }

    /**
     * Reset identity/auto-increment for a table (used after full delete).
     */
    private function resetIdentity(string $tableName): void
    {
        try {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'sqlsrv') {
                DB::statement("DBCC CHECKIDENT ('[{$tableName}]', RESEED, 0)");
            } elseif ($driver === 'pgsql') {
                DB::statement("SELECT setval(pg_get_serial_sequence('{$tableName}', 'id'), 1, false)");
            } else {
                DB::statement("ALTER TABLE `{$tableName}` AUTO_INCREMENT = 1");
            }
        } catch (\Exception $e) {
            Log::warning("Gagal reset identity untuk tabel {$tableName}: " . $e->getMessage());
        }
    }

    /**
     * Ensure upload_index column exists on the target table for versioned datasets (strict mode).
     */
    private function ensureUploadIndexColumn(string $tableName, string $connection): void
    {
        $schema = Schema::connection($connection);
        if (! $schema->hasColumn($tableName, 'upload_index')) {
            $schema->table($tableName, function (Blueprint $table) {
                $table->integer('upload_index')->nullable()->index();
            });
            Log::info("Added upload_index column on {$tableName} (connection: {$connection}) for dataset versioning");
        }
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

    /**
     * Detect companion legacy index table (e.g., admin_INDEX) and its key columns.
     */
    private function detectLegacyIndexTable(string $baseTable, string $connection): ?array
    {
        $indexTable = $baseTable . '_INDEX';
        if (!Schema::connection($connection)->hasTable($indexTable)) {
            return null;
        }

        $columns = Schema::connection($connection)->getColumnListing($indexTable);
        $indexColumn = collect($columns)->first(function ($col) {
            return in_array(strtolower($col), ['upload_index', 'index', 'idx'], true);
        });
        $activeColumn = collect($columns)->first(function ($col) {
            return in_array(strtolower($col), ['is_active', 'active', 'status'], true);
        });
        if (!$indexColumn || !$activeColumn) {
            return null;
        }

        $periodColumn = collect($columns)->first(function ($col) {
            return in_array(strtolower($col), ['period_date', 'period', 'periode'], true);
        });

        return [
            'table' => $indexTable,
            'index_column' => $indexColumn,
            'active_column' => $activeColumn,
            'period_column' => $periodColumn,
            'has_created_at' => in_array('created_at', $columns, true),
            'has_updated_at' => in_array('updated_at', $columns, true),
        ];
    }

    /**
     * Get the currently active upload_index from legacy *_INDEX table when present.
     */
    private function getActiveUploadIndexFromLegacy(string $baseTable, string $connection, ?string $periodDate = null): ?int
    {
        $meta = $this->detectLegacyIndexTable($baseTable, $connection);
        if (!$meta) {
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
            Log::warning('Gagal membaca legacy index table', [
                'table' => $meta['table'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Ambil nilai maksimum upload_index dari tabel utama dan/atau tabel legacy *_INDEX.
     */
    private function getExistingUploadIndexCeiling(string $baseTable, string $connection, ?string $periodDate = null): ?int
    {
        $max = null;

        if (Schema::connection($connection)->hasTable($baseTable) && Schema::connection($connection)->hasColumn($baseTable, 'upload_index')) {
            $columns = Schema::connection($connection)->getColumnListing($baseTable);
            $query = DB::connection($connection)->table($baseTable);
            if ($periodDate && in_array('period_date', $columns, true)) {
                $query->whereDate('period_date', $periodDate);
            }
            $val = $query->max('upload_index');
            if (is_numeric($val)) {
                $max = (int) $val;
            }
        }

        $legacyMeta = $this->detectLegacyIndexTable($baseTable, $connection);
        if ($legacyMeta) {
            $query = DB::connection($connection)->table($legacyMeta['table']);
            if ($periodDate && $legacyMeta['period_column']) {
                $query->whereDate($legacyMeta['period_column'], $periodDate);
            }
            $val = $query->max($legacyMeta['index_column']);
            if (is_numeric($val)) {
                $max = $max === null ? (int) $val : max($max, (int) $val);
            }
        }

        return $max;
    }

    /**
     * Sync legacy *_INDEX table to mark the provided upload_index as active.
     */
    private function syncLegacyIndexTable(string $baseTable, string $connection, int $uploadIndex, ?string $periodDate = null): void
    {
        $meta = $this->detectLegacyIndexTable($baseTable, $connection);
        if (!$meta) {
            return;
        }

        try {
            $conn = DB::connection($connection);
            $now = now();
            $table = $conn->table($meta['table']);

            // Deactivate previous entries
            $inactivePayload = [$meta['active_column'] => 0];
            if ($meta['has_updated_at']) {
                $inactivePayload['updated_at'] = $now;
            }
            $table->update($inactivePayload);

            // Upsert the new active index
            $activePayload = [
                $meta['index_column'] => $uploadIndex,
                $meta['active_column'] => 1,
            ];
            if ($meta['period_column'] && $periodDate) {
                $activePayload[$meta['period_column']] = $periodDate;
            }
            if ($meta['has_updated_at']) {
                $activePayload['updated_at'] = $now;
            }
            if ($meta['has_created_at']) {
                $activePayload['created_at'] = $now;
            }

            $existing = $table->where($meta['index_column'], $uploadIndex);
            if ($meta['period_column'] && $periodDate) {
                $existing->whereDate($meta['period_column'], $periodDate);
            }

            if ($existing->exists()) {
                $existing->update($activePayload);
            } else {
                $table->insert($activePayload);
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal menyinkronkan legacy index table', [
                'base_table' => $baseTable,
                'index_table' => $meta['table'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update UploadRun progress/status if run id is provided.
     */
    private function updateUploadRunProgress(?int $uploadRunId, int $percent, ?string $status = null, ?string $message = null): void
    {
        if (! $uploadRunId) {
            return;
        }
        try {
            $payload = [
                'progress_percent' => $percent,
                'updated_at' => now(),
            ];
            if ($status) {
                $payload['status'] = $status;
            }
            if ($message !== null) {
                $payload['message'] = $message;
            }
            UploadRun::where('id', $uploadRunId)->update($payload);
        } catch (\Throwable $e) {
            Log::warning('Failed to update upload run progress', [
                'run_id' => $uploadRunId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build strict versioned table name using period and upload index.
     */
    private function buildStrictVersionTableName(string $baseTable, string $periodDate, int $uploadIndex): string
    {
        $safePeriod = str_replace('-', '_', date('Y-m-d', strtotime($periodDate)));
        return strtolower($baseTable . '__p' . $safePeriod . '__i' . $uploadIndex);
    }

    /**
     * Ensure strict version table exists with the expected schema (id, mapped cols, period_date, upload_index, timestamps).
     */
    private function ensureStrictVersionTable(string $tableName, string $connection, $mappingRules, array $uniqueKeyColumns): void
    {
        $schema = Schema::connection($connection);
        if ($schema->hasTable($tableName)) {
            // Ensure required columns exist
            if (! $schema->hasColumn($tableName, 'period_date')) {
                $schema->table($tableName, function (Blueprint $table) {
                    $table->date('period_date')->nullable()->index();
                });
            }
            if (! $schema->hasColumn($tableName, 'upload_index')) {
                $schema->table($tableName, function (Blueprint $table) {
                    $table->integer('upload_index')->nullable()->index();
                });
            }
            return;
        }

        $schema->create($tableName, function (Blueprint $table) use ($mappingRules, $uniqueKeyColumns) {
            $table->bigIncrements('id');
            foreach ($mappingRules as $rule) {
                $columnName = $rule->table_column_name;
                if (in_array($columnName, $uniqueKeyColumns, true)) {
                    $table->string($columnName, 450)->nullable();
                } else {
                    $table->text($columnName)->nullable();
                }
            }
            $table->date('period_date')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('upload_index')->nullable()->index();
            $table->timestamps();

            if (!empty($uniqueKeyColumns)) {
                $table->unique($uniqueKeyColumns, $table->getTable() . '_unique_key');
            }
        });
    }

    private function reseedStrictVersionIdentity(string $baseTable, string $versionTable, string $connection): void
    {
        try {
            $conn = DB::connection($connection);
            if ($conn->getDriverName() !== 'sqlsrv') {
                return;
            }
            $schema = Schema::connection($connection);
            if (! $schema->hasTable($baseTable) || ! $schema->hasTable($versionTable)) {
                return;
            }
            if (! $schema->hasColumn($baseTable, 'id')) {
                return;
            }

            $baseMaxId = $conn->table($baseTable)->max('id');
            if (! is_numeric($baseMaxId)) {
                return;
            }
            $baseMaxId = (int) $baseMaxId;
            if ($baseMaxId <= 0) {
                return;
            }

            $conn->statement("DBCC CHECKIDENT ('[{$versionTable}]', RESEED, {$baseMaxId})");
        } catch (\Throwable $e) {
            Log::warning('Gagal reseed identity untuk strict version table', [
                'base_table' => $baseTable,
                'version_table' => $versionTable,
                'connection' => $connection,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Drop old strict version tables for a specific period (keep newest $keepCount inactive versions).
     */
    private function cleanupOldStrictTables(int $mappingId, string $connection, string $periodDate, string $baseTable, int $keepCount = 1): void
    {
        $runs = DB::table('mapping_upload_runs')
            ->where('mapping_index_id', $mappingId)
            ->where('period_date', $periodDate)
            ->where('status', 'inactive')
            ->orderByDesc('upload_index')
            ->skip($keepCount)
            ->take(50)
            ->get();

        foreach ($runs as $run) {
            $table = $this->buildStrictVersionTableName($baseTable, $periodDate, (int) $run->upload_index);
            if (Schema::connection($connection)->hasTable($table)) {
                Schema::connection($connection)->drop($table);
                Log::info("Dropped old strict version table {$table} (mapping {$mappingId}, period {$periodDate})");
            }
        }
    }

    public function queueUpload(Request $request): JsonResponse
    {
        $rawPeriod = $request->input('period_date');
        if ($rawPeriod === '' || $rawPeriod === null) {
            $request->merge(['period_date' => null]);
        }

        $validated = $request->validate([
            'data_file' => ['required', File::types(['xlsx', 'xls'])],
            'mapping_id' => ['required', 'integer', Rule::exists('mapping_indices', 'id')],
            'selected_columns' => ['nullable', 'string'],
            'upload_mode' => ['required', 'string', Rule::in(['upsert', 'strict'])],
            'sheet_name' => ['nullable', 'string'],
            'period_date' => ['nullable', 'required_if:upload_mode,strict', 'date_format:Y-m-d'],
        ], [
            'period_date.date_format' => 'Period harus menggunakan format YYYY-MM-DD.',
        ]);

        $uploadedFile = $request->file('data_file');
        Storage::disk('local')->makeDirectory('tmp');
        $storedName = 'upload_' . Str::random(16) . '.xlsx';
        $storedRelativePath = $uploadedFile->storeAs('tmp', $storedName, 'local');
        if (! $storedRelativePath) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan file upload.',
            ], 500);
        }
        $storedXlsxPath = Storage::disk('local')->path($storedRelativePath);

        $run = UploadRun::create([
            'mapping_index_id' => $validated['mapping_id'],
            'user_id' => Auth::id(),
            'file_name' => $uploadedFile->getClientOriginalName(),
            'stored_xlsx_path' => $storedXlsxPath,
            'sheet_name' => $validated['sheet_name'] ?? null,
            'upload_mode' => $validated['upload_mode'],
            'period_date' => $validated['period_date'] ?? null,
            'selected_columns' => $validated['selected_columns'] ?? null,
            'status' => 'pending',
            'progress_percent' => 0,
        ]);

        ProcessUploadJob::dispatch($run->id);

        return response()->json([
            'success' => true,
            'message' => 'Upload sedang diantrikan. Pantau progres di kartu \"Recent Uploads\".',
        ], 202);
    }

    /**
     * Upload data - Process actual upload with STAGING TABLE pattern
     */
    public function uploadData(Request $request): JsonResponse
    {
        Log::info('=== MEMULAI UPLOAD DATA DENGAN STAGING TABLE PATTERN ===');
        
        $stagingTableName = null;
        $totalRows = 0;
        $chunksInserted = 0;
        $cancelKey = null;
        $csvPath = null;
        $csvHandle = null;
        $useBulkInsert = false;
        $rowsSinceLog = 0;
        $convertedCsvPath = null;
        $storedXlsxPath = null;
        /** @var UploadIndexService $uploadIndexService */
        $uploadIndexService = app(UploadIndexService::class);
        $uploadRun = null;
        $uploadIndexValue = null;
        $useUploadIndex = false;
        
        try {
            if ($request->filled('run_id')) {
                $uploadRun = UploadRun::find($request->input('run_id'));
            }
            // Allow PHP to notice when client disconnects so we can stop early
            @ignore_user_abort(false);
            @set_time_limit(0);
            @ini_set('max_execution_time', '0');
            $rawPeriod = $request->input('period_date');
            if ($rawPeriod === '' || $rawPeriod === null) {
                $request->merge(['period_date' => null]);
            }

            $validated = $request->validate([
                'data_file' => ['required', File::types(['xlsx', 'xls'])],
                'mapping_id' => ['required', 'integer', Rule::exists('mapping_indices', 'id')],
                'selected_columns' => ['nullable', 'string'],
                'upload_mode' => ['required', 'string', Rule::in(['upsert', 'strict'])],
                'sheet_name' => ['nullable', 'string'],
                'period_date' => ['nullable', 'required_if:upload_mode,strict', 'date_format:Y-m-d'],
            ], [
                'period_date.date_format' => 'Period harus menggunakan format YYYY-MM-DD.',
            ]);
            
            // Use provided period_date (strict) or today's date
            $periodDate = $validated['period_date'] ?? now()->toDateString();
            // Use ISO 8601 (T separator) for datetime to keep SQL Server bulk insert happy
            $nowString = now()->format('Y-m-d\\TH:i:s.v');
            // Remove delimiter/newlines that could break bulk parsing
            $nowStringSanitized = str_replace(['|', "\r", "\n"], ' ', $nowString);
            Log::info("Menggunakan period_date: {$periodDate}");

            $mapping = MappingIndex::with('columns')->find($validated['mapping_id']);
            
            if (!$mapping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format tidak ditemukan.'
                ]);
            }

            // Build cancel key (one active upload per mapping per user)
            $cancelKey = 'upload_cancel_' . $mapping->id . '_' . Auth::id();
            Cache::forget($cancelKey);

            $mainTableName = $mapping->table_name;
            $headerRow = $mapping->header_row;
            $headerRowIndex = max(0, $headerRow - 1);
            $uploadMode = $validated['upload_mode'];

            // Tentukan koneksi target; fallback ke legacy jika tabel ada di legacy saja
            $connection = $mapping->target_connection
                ?? $mapping->connection
                ?? config('database.default');

            $this->ensureLegacyConnectionConfigured($connection);
            if (! Schema::connection($connection)->hasTable($mainTableName) && Schema::connection('sqlsrv_legacy')->hasTable($mainTableName)) {
                $connection = 'sqlsrv_legacy';
            }

            DB::setDefaultConnection($connection);
            $schema = Schema::connection($connection);
            $hasPeriodDate = $schema->hasColumn($mainTableName, 'period_date');
            
            Log::info('Mapping info:', [
                'id' => $mapping->id,
                'description' => $mapping->description,
                'table_name' => $mainTableName,
                'header_row' => $headerRow,
                'upload_mode' => $uploadMode,
                'driver' => DB::connection()->getDriverName(),
                'connection' => $connection,
            ]);

            if (!$mainTableName || ! $schema->hasTable($mainTableName)) {
                Log::error("Tabel utama tidak ditemukan: {$mainTableName}");
                return response()->json([
                    'success' => false,
                    'message' => "Tabel '{$mainTableName}' tidak ditemukan di database."
                ]);
            }

            // Parse selected columns
            $selectedColumns = null;
            if (!empty($validated['selected_columns'])) {
                $selectedColumns = json_decode($validated['selected_columns'], true);
                Log::info('Selected columns:', $selectedColumns);
            }

            // Get mapping rules and filter by selected columns
            $mappingRules = $mapping->columns;
            
            if ($selectedColumns !== null && is_array($selectedColumns)) {
                $mappingRules = $mappingRules->filter(function ($rule) use ($selectedColumns) {
                    return isset($selectedColumns[$rule->excel_column_index]) 
                        && !empty($selectedColumns[$rule->excel_column_index]);
                });
                Log::info('Filtered mapping rules count: ' . $mappingRules->count());
            }

            if ($mappingRules->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada kolom yang dipilih untuk diimport.'
                ]);
            }

            // Get unique key columns for upsert
            $uniqueKeyColumns = $mappingRules->where('is_unique_key', true)
                ->pluck('table_column_name')
                ->toArray();
            
            Log::info('Unique key columns:', $uniqueKeyColumns);

            // If upsert mode but no unique keys, choose fallback:
            // - For legacy: append (no delete, just insert)
            // - For others: append as well (avoid accidental replace)
            if ($uploadMode === 'upsert' && empty($uniqueKeyColumns)) {
                Log::warning('Upsert mode selected but no unique keys defined. Switching to append mode to avoid data replacement.', [
                    'mapping_id' => $mapping->id,
                    'table' => $mainTableName,
                    'connection' => $connection,
                ]);
                $uploadMode = 'append';
            }
            $useUploadIndex = ($uploadMode === 'strict');
            $legacyIndexMeta = $useUploadIndex ? $this->detectLegacyIndexTable($mainTableName, $connection) : null;
            $useInlineIndexTable = $useUploadIndex && $legacyIndexMeta !== null;
            $existingIndexCeiling = $useUploadIndex
                ? $this->getExistingUploadIndexCeiling($mainTableName, $connection, $periodDate)
                : null;
            if ($useUploadIndex && !$hasPeriodDate) {
                $hasPeriodDate = true;
            }

            if ($useUploadIndex) {
                if (empty($validated['period_date'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Period date wajib diisi untuk mode strict.'
                    ], 422);
                }

                $this->ensureUploadIndexColumn($mainTableName, $connection);
                $uploadRun = $uploadIndexService->beginRun($mapping->id, Auth::id(), $periodDate, $existingIndexCeiling);
                $uploadIndexValue = (int) $uploadRun->upload_index;

                if ($legacyIndexMeta) {
                    Log::info('Legacy index table detected for strict mode', [
                        'index_table' => $legacyIndexMeta['table'],
                        'ceiling' => $existingIndexCeiling,
                        'next_upload_index' => $uploadIndexValue,
                    ]);
                }
            }

            // Create mapping array: excel_column_index => table_column_name
            $columnMapping = $mappingRules->pluck('table_column_name', 'excel_column_index')->toArray();
            Log::info('Column mapping:', $columnMapping);

            $targetTableName = $mainTableName;
            $versionTableName = null;
            if ($useUploadIndex && ! $useInlineIndexTable) {
                $versionTableName = $this->buildStrictVersionTableName($mainTableName, $periodDate, $uploadIndexValue);
                $this->ensureStrictVersionTable($versionTableName, $connection, $mappingRules, $uniqueKeyColumns);
                $this->reseedStrictVersionIdentity($mainTableName, $versionTableName, $connection);
                $targetTableName = $versionTableName;
            } elseif ($useUploadIndex && $useInlineIndexTable) {
                Log::info('Using inline upload_index versioning on base table (legacy *_INDEX detected)', [
                    'table' => $mainTableName,
                    'upload_index' => $uploadIndexValue,
                ]);
            }

            // Determine sheet (use provided or first)
            $sheetName = $validated['sheet_name'] ?? ($this->listWorksheetNames($request->file('data_file'))[0] ?? 'Sheet1');
            Log::info('Sheet selected for upload: ' . $sheetName);

            // Read header row only (for validation)
            $headers = $this->loadHeaderRow($request->file('data_file'), $headerRowIndex, $sheetName);
            if (empty($headers)) {
                return response()->json([
                    'success' => false,
                    'message' => "Sheet '{$sheetName}' tidak memiliki header pada baris {$headerRow}.",
                ]);
            }

            // Stream data rows after header
            $uploadedFile = $request->file('data_file');
            $storedName = 'upload_' . Str::random(16) . '.xlsx';
            Storage::disk('local')->makeDirectory('tmp');
            $storedRelativePath = $uploadedFile->storeAs('tmp', $storedName, 'local');
            if (! $storedRelativePath) {
                throw new \RuntimeException('Gagal menyimpan file upload ke storage/tmp');
            }
            $excelPath = Storage::disk('local')->path($storedRelativePath);
            $storedXlsxPath = $excelPath;
            $aborted = false;
            $tmpDir = storage_path('app/tmp');
            if (!is_dir($tmpDir)) {
                mkdir($tmpDir, 0777, true);
            }
            // Use a pipe delimiter for SQL Server bulk insert to avoid commas inside data shifting columns
            $bulkDelimiter = '|';
            $orderedColumns = array_values($columnMapping);
            $stagingColumns = array_merge(
                $orderedColumns,
                $hasPeriodDate ? ['period_date'] : [],
                $useUploadIndex ? ['upload_index'] : [],
                ['created_at', 'updated_at']
            );
            $columnIndexes = [];
            foreach ($columnMapping as $excelColumn => $dbColumn) {
                // CSV index is 0-based
                $columnIndexes[$dbColumn] = $this->columnLetterToIndex($excelColumn);
            }
            $dataStartRow = max(1, $headerRow + 1); // 1-based row where data begins

            // ========================================
            // STAGING TABLE PATTERN IMPLEMENTATION
            // ========================================
            
            $driver = DB::connection()->getDriverName();

            // Step 1: Create staging table with unique random name
            $stagingTableName = 'staging_' . $mainTableName . '_' . Str::random(8);
            Log::info("Step 1: Creating staging table: {$stagingTableName}");
            $this->updateUploadRunProgress($uploadRun?->id, 10);
            
            // Create staging table with identical structure (no unique constraint to allow dedup after bulk insert)
            Schema::connection($connection)->create($stagingTableName, function (Blueprint $table) use ($columnMapping, $uniqueKeyColumns, $driver, $uploadMode, $hasPeriodDate, $useUploadIndex) {
                // Add all mapped columns as text/nullable (same as main table)
                foreach ($columnMapping as $dbColumn) {
                    if (in_array($dbColumn, $uniqueKeyColumns, true)) {
                        $table->string($dbColumn, 450)->nullable();
                    } else {
                        $table->text($dbColumn)->nullable();
                    }
                }
                
                if ($hasPeriodDate) {
                    $table->date('period_date')->nullable();
                }
                if ($useUploadIndex) {
                    $table->integer('upload_index')->nullable()->index();
                }
                // Keep timestamps as strings to avoid bulk insert conversion issues
                $table->string('created_at', 30)->nullable();
                $table->string('updated_at', 30)->nullable();
            });
            
            Log::info("Staging table '{$stagingTableName}' created successfully");

            // SQL Server: use BULK INSERT via temp CSV for speed
            // Only use BULK INSERT on SQL Server default connection (never on legacy connections to avoid codepage/type issues)
            $useBulkInsert = $driver === 'sqlsrv'
                && $connection === config('database.default')
                && $connection !== 'sqlsrv_legacy'
                && empty($mapping->connection)
                && empty($mapping->target_connection);
            if ($useBulkInsert) {
                $csvPath = $tmpDir . '/bulk_' . $stagingTableName . '_' . Str::random(6) . '.csv';
                $csvHandle = fopen($csvPath, 'w');
                if (!$csvHandle) {
                    throw new \RuntimeException("Gagal membuka file CSV sementara: {$csvPath}");
                }
            }
            
            // Step 2: Stream insert rows into staging to avoid high memory usage
            Log::info("Step 2: Inserting rows into staging table (streaming)");

            $columnsPerRow = count($stagingColumns);
            $driver = DB::connection()->getDriverName();

            // Calculate a safe base chunk size from parameter limits
            $maxParams = $driver === 'sqlsrv' ? 2000 : 10000;
            $baseChunk = max(1, intdiv($maxParams, max(1, $columnsPerRow)));

            // SQL Server: capped to stay under 1000 row-value limit per INSERT VALUES
            // Others: still allow larger batches
            if ($driver === 'sqlsrv') {
                $chunkSize = 900;
            } else {
                $chunkSize = min(2000, max(500, $baseChunk));
            }

            $chunk = [];
            $totalRows = 0;
            $chunksInserted = 0;
            $rowsSinceLog = 0;
            // Faster XLSX -> CSV conversion via qsv
            $convertedCsvPath = $tmpDir . '/qsv_input_' . Str::random(8) . '.csv';
            $convertStart = microtime(true);
            Log::info('QSV convert start', [
                'xlsx' => $excelPath,
                'csv' => $convertedCsvPath,
                'sheet' => $sheetName,
            ]);
            app(QsvExcelConverter::class)->convertXlsxToCsv($excelPath, $convertedCsvPath, $sheetName);
            $convertDuration = round(microtime(true) - $convertStart, 2);
            Log::info('QSV convert finished', [
                'xlsx' => $excelPath,
                'csv' => $convertedCsvPath,
                'sheet' => $sheetName,
                'seconds' => $convertDuration,
            ]);
            $this->updateUploadRunProgress($uploadRun?->id, 25);
            $streamStart = microtime(true);

            $csvInput = fopen($convertedCsvPath, 'r');
            if (! $csvInput) {
                throw new \RuntimeException("Gagal membuka hasil konversi qsv: {$convertedCsvPath}");
            }

            $rowNumber = 0;
            while (($row = fgetcsv($csvInput)) !== false) {
                $rowNumber++;

                if ($rowNumber < $dataStartRow) {
                    continue; // Skip header rows
                }

                if ($cancelKey && Cache::get($cancelKey)) {
                    Log::warning("Upload canceled via flag before processing row {$rowNumber}");
                    $aborted = true;
                    break;
                }

                if (function_exists('connection_aborted') && connection_aborted()) {
                    Log::warning("Upload aborted by client before processing row {$rowNumber}.");
                    $aborted = true;
                    break;
                }

                $rowValues = [];
                $isEmpty = true;

                foreach ($orderedColumns as $dbColumn) {
                    $colIndex = $columnIndexes[$dbColumn] ?? null;
                    if ($colIndex === null) {
                        $rowValues[] = null;
                        continue;
                    }

                    $cellValue = $row[$colIndex] ?? null;

                    if (is_string($cellValue)) {
                        $cellValue = trim($cellValue);

                        // For bulk insert, strip delimiter/newline characters that would break row parsing
                        if ($useBulkInsert) {
                            $cellValue = str_replace([$bulkDelimiter, "\r", "\n"], ' ', $cellValue);
                        }
                    }

                    if ($cellValue === '') {
                        $cellValue = null;
                    }

                    if ($cellValue !== null && $cellValue !== '') {
                        $isEmpty = false;
                    }

                    // Convert Excel date serials/strings for transaction_date column
                    if ($dbColumn === 'transaction_date' && $cellValue !== null && $cellValue !== '') {
                        $converted = $this->convertExcelDate($cellValue);
                        $cellValue = $converted;
                    }

                    $rowValues[] = $cellValue;
                }

                if ($isEmpty) {
                    continue;
                }

                // Append audit columns
                if ($hasPeriodDate) {
                    $rowValues[] = $periodDate;
                }
                if ($useUploadIndex) {
                    $rowValues[] = $uploadIndexValue;
                }
                $rowValues[] = $nowStringSanitized;
                $rowValues[] = $nowStringSanitized;

                if ($useBulkInsert) {
                    // Write CSV line without adding quote enclosures so spaces stay clean
                    $safeRow = array_map(function ($v) {
                        return $v === null ? '' : $v;
                    }, $rowValues);
                    fwrite($csvHandle, implode($bulkDelimiter, $safeRow) . PHP_EOL);
                } else {
                    $chunk[] = array_combine($stagingColumns, $rowValues);
                    if (count($chunk) >= $chunkSize) {
                        DB::table($stagingTableName)->insert($chunk);
                        $chunksInserted++;
                        $chunk = [];
                        @set_time_limit(0);
                    }
                }

                $totalRows++;
                $rowsSinceLog++;

                if ($rowsSinceLog >= 5000) {
                    Log::info("Upload progress: {$totalRows} rows processed so far (chunk size {$chunkSize})");
                    $rowsSinceLog = 0;
                }
            }

            fclose($csvInput);
            @unlink($convertedCsvPath);

            $streamDuration = round(microtime(true) - $streamStart, 2);
            Log::info("Streaming finished: {$totalRows} rows written" . ($useBulkInsert ? " to CSV {$csvPath}" : '') . " in {$streamDuration}s");

            if ($useBulkInsert && $csvHandle) {
                fclose($csvHandle);
                $csvHandle = null;
            }

            if (!$useBulkInsert && !$aborted && !empty($chunk)) {
                DB::table($stagingTableName)->insert($chunk);
                $chunksInserted++;
            }

            if ($aborted) {
                Log::warning("Upload aborted; cleaning up staging table {$stagingTableName}");
                if ($csvPath && file_exists($csvPath)) {
                    @unlink($csvPath);
                }
                if ($convertedCsvPath && file_exists($convertedCsvPath)) {
                    @unlink($convertedCsvPath);
                }
                if ($storedXlsxPath && file_exists($storedXlsxPath)) {
                    @unlink($storedXlsxPath);
                }
                if ($uploadRun && $useUploadIndex) {
                    $uploadIndexService->failRun($uploadRun, 'Upload dibatalkan oleh pengguna');
                }
                Schema::dropIfExists($stagingTableName);
                if ($cancelKey) {
                    Cache::forget($cancelKey);
                }
                return response()->json([
                    'success' => false,
                    'message' => 'Upload dibatalkan.'
                ], 499); // 499 = Client Closed Request (nginx convention)
            }

            if ($totalRows === 0) {
                if ($csvPath && file_exists($csvPath)) {
                    @unlink($csvPath);
                }
                Schema::dropIfExists($stagingTableName);
                if ($uploadRun && $useUploadIndex) {
                    $uploadIndexService->failRun($uploadRun, 'Tidak ada data valid di file.');
                }
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data valid yang dapat diimpor dari file.'
                ]);
            }

            // SQL Server bulk insert from CSV if enabled
            if ($useBulkInsert && $csvPath) {
                $pathForSql = str_replace('\\', '\\\\', $csvPath);
                $pathForSql = str_replace("'", "''", $pathForSql);
                // Use BULK INSERT without column list (table has no identity column)
                $bulkSql = "BULK INSERT [{$stagingTableName}]
                            FROM '{$pathForSql}'
                            WITH (
                                FIRSTROW = 1,
                                FIELDTERMINATOR = '{$bulkDelimiter}',
                                ROWTERMINATOR = '0x0a',
                                KEEPNULLS,
                                CODEPAGE = '65001',
                                TABLOCK
                            )";
                Log::info("Executing BULK INSERT from {$csvPath}");
                DB::statement($bulkSql);
                @unlink($csvPath);
                Log::info("BULK INSERT completed for {$stagingTableName} (rows: {$totalRows})");
                $this->updateUploadRunProgress($uploadRun?->id, 55);
            }

            Log::info('Total rows to process: ' . $totalRows . " | chunks: {$chunksInserted} | chunkSize: {$chunkSize}");
            
            // Deduplicate staging rows on unique keys (keep first) to avoid duplicate key errors during sync
            if (!empty($uniqueKeyColumns) && $driver === 'sqlsrv') {
                // Add surrogate row id for dedup
                DB::statement("ALTER TABLE [{$stagingTableName}] ADD __row_id BIGINT IDENTITY(1,1);");

                $partition = implode(', ', array_map(fn($col) => "[{$col}]", $uniqueKeyColumns));
                $notNullFilter = implode(' AND ', array_map(fn($col) => "[{$col}] IS NOT NULL", $uniqueKeyColumns));
                $dedupSql = "
                    WITH cte AS (
                        SELECT __row_id, ROW_NUMBER() OVER (PARTITION BY {$partition} ORDER BY __row_id) AS rn
                        FROM [{$stagingTableName}]
                        WHERE {$notNullFilter}
                    )
                    DELETE FROM cte WHERE rn > 1;
                ";
                $removed = DB::affectingStatement($dedupSql);
                Log::info("Staging dedup applied on unique keys (" . implode(', ', $uniqueKeyColumns) . "), rows removed: {$removed}");
                $this->updateUploadRunProgress($uploadRun?->id, 65);
            }
            
            // Step 3: Atomic transaction to sync from staging to main table
            Log::info("Step 3: Starting atomic transaction to sync data");
            
            $driver = DB::connection()->getDriverName();
            DB::beginTransaction();
            
            try {
                if ($uploadMode === 'append') {
                    // APPEND MODE: Insert all staging rows as-is (with safe conversions)
                    Log::info("APPEND MODE: Inserting data without deletion for table '{$targetTableName}'");

                    $columns = array_merge(
                        array_values($columnMapping),
                        $hasPeriodDate ? ['period_date'] : [],
                        $useUploadIndex ? ['upload_index'] : [],
                        ['created_at', 'updated_at']
                    );

                    $columnTypes = [];
                    foreach ($columns as $col) {
                        try {
                            $columnTypes[$col] = $schema->getColumnType($targetTableName, $col);
                        } catch (\Exception $e) {
                            $columnTypes[$col] = 'string';
                        }
                    }

                    $selects = [];
                    foreach ($columns as $col) {
                        $type = $columnTypes[$col] ?? 'string';
                        $expr = "[{$col}]";
                        if (in_array($type, ['integer', 'bigint', 'smallint', 'tinyint'], true)) {
                            $expr = "TRY_CONVERT(INT, [{$col}])";
                        } elseif (in_array($type, ['decimal', 'float', 'double'], true)) {
                            $expr = "TRY_CONVERT(DECIMAL(18,2), [{$col}])";
                        } elseif ($type === 'date') {
                            $expr = "TRY_CONVERT(DATE, [{$col}])";
                        } elseif ($type === 'datetime' || $type === 'datetimetz') {
                            $expr = "TRY_CONVERT(DATETIME, [{$col}])";
                        }
                        $selects[] = DB::raw("{$expr} as [{$col}]");
                    }

                    DB::table($targetTableName)->insertUsing(
                        $columns,
                        DB::table($stagingTableName)->select($selects)
                    );

                    $message = $totalRows . " baris data berhasil ditambahkan ke tabel '{$targetTableName}' (Mode Append).";

                } elseif ($uploadMode === 'strict') {
                    if ($useUploadIndex && $uploadIndexValue === null) {
                        throw new \RuntimeException('upload_index run belum diinisialisasi.');
                    }
                    // STRICT MODE (versioned): keep previous versions, insert new dataset with upload_index
                    Log::info("STRICT MODE (versioned): inserting dataset upload_index {$uploadIndexValue} for table '{$targetTableName}'");

                    $columns = array_merge(
                        array_values($columnMapping),
                        $hasPeriodDate ? ['period_date'] : [],
                        ['upload_index'],
                        ['created_at', 'updated_at']
                    );

                    // Ensure we don't duplicate the same upload_index if a retry happens
                    DB::table($targetTableName)
                        ->where('upload_index', $uploadIndexValue)
                        ->delete();

                    // Build safe select with TRY_CONVERT for numeric/date columns to avoid type errors on legacy DB
                    $columnTypes = [];
                    foreach ($columns as $col) {
                        try {
                            $columnTypes[$col] = $schema->getColumnType($targetTableName, $col);
                        } catch (\Exception $e) {
                            $columnTypes[$col] = 'string';
                        }
                    }

                    $selects = [];
                    foreach ($columns as $col) {
                        $type = $columnTypes[$col] ?? 'string';
                        $expr = "[{$col}]";
                        if (in_array($type, ['integer', 'bigint', 'smallint', 'tinyint'], true)) {
                            $expr = "TRY_CONVERT(INT, [{$col}])";
                        } elseif ($type === 'decimal' || $type === 'float' || $type === 'double') {
                            $expr = "TRY_CONVERT(DECIMAL(18,2), [{$col}])";
                        } elseif ($type === 'date') {
                            $expr = "TRY_CONVERT(DATE, [{$col}])";
                        } elseif ($type === 'datetime' || $type === 'datetimetz') {
                            $expr = "TRY_CONVERT(DATETIME, [{$col}])";
                        }
                        $selects[] = DB::raw("{$expr} as [{$col}]");
                    }

                    DB::table($targetTableName)->insertUsing(
                        $columns,
                        DB::table($stagingTableName)->select($selects)
                    );
                    
                    Log::info("STRICT MODE: Successfully inserted versioned dataset (upload_index {$uploadIndexValue})");
                    $message = $totalRows . " baris data berhasil diimpor (Strict, upload_index {$uploadIndexValue}).";
                    
                    $this->updateUploadRunProgress($uploadRun?->id, 85);
                    
                } else {
                    // UPSERT MODE
                    $dataColumns = array_values($columnMapping);
                    $allColumns = array_merge(
                        $dataColumns,
                        $hasPeriodDate ? ['period_date'] : [],
                        ['created_at', 'updated_at']
                    );
                    
                    if ($driver === 'sqlsrv') {
                        // SQL Server uses MERGE for upsert
                    Log::info("UPSERT MODE: Using MERGE for unique keys: " . implode(', ', $uniqueKeyColumns));
                        
                        $onClause = implode(' AND ', array_map(fn($col) => "target.[{$col}] = source.[{$col}]", $uniqueKeyColumns));
                        $updateSetParts = array_map(fn($col) => "target.[{$col}] = source.[{$col}]", $dataColumns);
                        $updateSetParts[] = "target.[updated_at] = source.[updated_at]";
                        $updateSet = implode(', ', $updateSetParts);
                        
                        $insertColumns = implode(', ', array_map(fn($col) => "[{$col}]", $allColumns));
                        $insertValues = implode(', ', array_map(fn($col) => "source.[{$col}]", $allColumns));
                        
                        $mergeSql = "MERGE INTO [{$mainTableName}] AS target
                                     USING [{$stagingTableName}] AS source
                                     ON {$onClause}
                                     WHEN MATCHED THEN
                                         UPDATE SET {$updateSet}
                                     WHEN NOT MATCHED BY TARGET THEN
                                         INSERT ({$insertColumns}) VALUES ({$insertValues});";
                        
                        DB::statement($mergeSql);
                    } else {
                        // PostgreSQL / others: use ON CONFLICT
                        Log::info("UPSERT MODE: Using ON CONFLICT for unique keys: " . implode(', ', $uniqueKeyColumns));
                        
                        $conflictTarget = implode(', ', array_map(fn($col) => '"' . $col . '"', $uniqueKeyColumns));
                        
                        // Update data columns and updated_at, keep period_date and created_at from original record
                        $updateClauses = [];
                        foreach ($dataColumns as $col) {
                            $updateClauses[] = "\"{$col}\" = EXCLUDED.\"{$col}\"";
                        }
                        $updateClauses[] = "\"updated_at\" = EXCLUDED.\"updated_at\"";
                        $updateSet = implode(', ', $updateClauses);
                        
                        $columnsList = implode(', ', array_map(fn($col) => '"' . $col . '"', $allColumns));
                        
                        $sql = "INSERT INTO \"{$mainTableName}\" ({$columnsList}) 
                                SELECT {$columnsList} FROM \"{$stagingTableName}\"
                                ON CONFLICT ({$conflictTarget}) DO UPDATE SET {$updateSet}";
                        
                        DB::statement($sql);
                    }
                    
                    Log::info("UPSERT MODE: Successfully synced data (period_date preserved for existing records)");
                    $message = $totalRows . " baris data berhasil diproses ke tabel '{$mainTableName}' (Mode Upsert: period_date tetap untuk data yang sudah ada).";
                }
                
                // Commit transaction
                DB::commit();
                Log::info("=== TRANSAKSI BERHASIL DI-COMMIT ===");
                
                if ($uploadRun && $useUploadIndex) {
                    $uploadIndexService->activateRun($uploadRun);

                    if ($legacyIndexMeta) {
                        $this->syncLegacyIndexTable($mainTableName, $connection, $uploadIndexValue, $periodDate);
                    }

                    if (! $useInlineIndexTable) {
                        CleanupStrictVersions::dispatch($mapping->id, $periodDate, $mainTableName, $connection)
                            ->delay(now()->addDay());
                    }
                }
                $this->updateUploadRunProgress($uploadRun?->id, 95);
                
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('=== TRANSAKSI DI-ROLLBACK ===');
                Log::error('Sync error: ' . $e->getMessage());
                Log::error('Sync context', [
                    'upload_mode' => $uploadMode,
                    'unique_keys' => $uniqueKeyColumns,
                    'total_rows' => $totalRows,
                    'chunks_inserted' => $chunksInserted ?? null,
                    'staging_table' => $stagingTableName,
                    'main_table' => $mainTableName,
                ]);
                throw $e; // Re-throw to outer catch block
            }
            
            // Step 4: Cleanup - Drop staging table
            Log::info("Step 4: Cleanup - Dropping staging table '{$stagingTableName}'");
            $this->dropStagingTable($stagingTableName, $connection);
            Log::info("Staging table dropped successfully");
            if ($storedXlsxPath && file_exists($storedXlsxPath)) {
                @unlink($storedXlsxPath);
            }
            
            // Step 5: Log the upload
            $previousConnection = DB::getDefaultConnection();
                DB::setDefaultConnection(config('database.default'));
            try {
                \App\Models\UploadLog::create([
                    'user_id' => Auth::id(),
                    'division_id' => Auth::user()->division_id,
                    'mapping_index_id' => $mapping->id,
                    'file_name' => $request->file('data_file')->getClientOriginalName(),
                    'rows_imported' => $totalRows,
                    'status' => 'success',
                    'action' => 'upload',
                    'upload_mode' => $uploadMode,
                    'error_message' => null,
                ]);
            } finally {
                DB::setDefaultConnection($previousConnection);
            }
            Log::info("Upload log created successfully");
            
            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            Log::error('=== UPLOAD FAILED ===');
            Log::error('Upload error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'mapping_id' => $request->input('mapping_id'),
                'upload_mode' => $request->input('upload_mode'),
                'selected_columns' => $request->input('selected_columns'),
                'sheet_name' => $request->input('sheet_name'),
                'rows_processed' => $totalRows,
                'chunks_inserted' => $chunksInserted ?? null,
                'file' => $request->file('data_file')?->getClientOriginalName(),
            ]);
            Log::error('Stack trace: ' . $e->getTraceAsString());
            Log::error('File: ' . $e->getFile());
            Log::error('Line: ' . $e->getLine());

            if ($uploadRun && $useUploadIndex) {
                $uploadIndexService->failRun($uploadRun, $e->getMessage());
            }
            
            // Cleanup: Drop staging table if it exists
            $safeConnection = $connection ?? config('database.default');
            $this->dropStagingTable($stagingTableName, $safeConnection, true);

            if ($csvPath && file_exists($csvPath)) {
                @unlink($csvPath);
            }
            if ($convertedCsvPath && file_exists($convertedCsvPath)) {
                @unlink($convertedCsvPath);
            }
            if ($storedXlsxPath && file_exists($storedXlsxPath)) {
                @unlink($storedXlsxPath);
            }
            
            if ($cancelKey) {
                Cache::forget($cancelKey);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal upload data: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Set cancel flag for an in-progress upload (per mapping per user).
     */
    public function cancelUpload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mapping_id' => ['required', 'integer', Rule::exists('mapping_indices', 'id')],
        ]);

        $mapping = MappingIndex::find($validated['mapping_id']);
        if (!$mapping) {
            return response()->json([
                'success' => false,
                'message' => 'Format tidak ditemukan.'
            ], 404);
        }

        $cancelKey = 'upload_cancel_' . $mapping->id . '_' . Auth::id();
        Cache::put($cancelKey, true, now()->addMinutes(10));
        Log::warning("Upload cancel flag set for mapping {$mapping->id} by user " . Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Upload dibatalkan (flag diset).'
        ]);
    }

    /**
     * Strict mode upload with auto-detected period and versioned tables for zero downtime.
     */
    public function uploadDataStrict(Request $request): JsonResponse
    {
        // Explicitly resolve the service
        /** @var \App\Services\UploadIndexService $uploadIndexService */
        $uploadIndexService = app(\App\Services\UploadIndexService::class);

        // 1. Validate Input (Period is required from user selection)
        $validated = $request->validate([
            'data_file' => ['required', File::types(['xlsx', 'xls', 'csv'])],
            'mapping_id' => ['required', 'integer', Rule::exists('mapping_indices', 'id')],
            'sheet_name' => ['nullable', 'string'],
            'period_date' => ['required', 'date_format:Y-m-d'],
        ], [
            'period_date.date_format' => 'Period harus menggunakan format YYYY-MM-DD.',
        ]);

        $mapping = MappingIndex::with('columns')->find($validated['mapping_id']);
        if (!$mapping) {
            return response()->json(['success' => false, 'message' => 'Format tidak ditemukan.'], 404);
        }

        // 2. Resolve Connection (Auto fallback to legacy)
        $connection = $mapping->target_connection 
            ?? $mapping->connection 
            ?? config('database.default');
        
        $baseTableName = $mapping->table_name;
        $this->ensureLegacyConnectionConfigured($connection);

        if (!Schema::connection($connection)->hasTable($baseTableName) && Schema::connection('sqlsrv_legacy')->hasTable($baseTableName)) {
            $connection = 'sqlsrv_legacy';
        }

        $legacyIndexMeta = $this->detectLegacyIndexTable($baseTableName, $connection);
        $useInlineIndexTable = $legacyIndexMeta !== null;
        if ($useInlineIndexTable) {
            $this->ensureUploadIndexColumn($baseTableName, $connection);
        }

        // 3. Find Mapping for period (support aliases)
        $periodColumnRule = $mapping->columns->first(function ($col) {
            $name = strtolower($col->table_column_name);
            return in_array($name, ['period', 'period_date', 'periode', 'period_dt', 'perioddate'], true);
        });
        if (!$periodColumnRule) {
            return response()->json(['success' => false, 'message' => 'Format ini belum memiliki mapping untuk kolom period.'], 422);
        }
        $periodColumnName = $periodColumnRule->table_column_name;
        if ($useInlineIndexTable) {
            $actualColumns = Schema::connection($connection)->getColumnListing($baseTableName);
            if (! in_array($periodColumnName, $actualColumns, true)) {
                return response()->json([
                    'success' => false,
                    'message' => "Kolom period '{$periodColumnName}' tidak ditemukan pada tabel legacy.",
                ], 422);
            }
        }

        // 4. Load file (period will follow user selection)
        $file = $request->file('data_file');
        $reader = IOFactory::createReaderForFile($file->getPathname());
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file->getPathname());
        
        $sheetName = $validated['sheet_name'] ?? null;
        $sheet = $sheetName ? $spreadsheet->getSheetByName($sheetName) : null;
        if (! $sheet) {
            $sheet = $spreadsheet->getSheet(0);
            $sheetName = $sheet ? $sheet->getTitle() : null;
        }
        if (! $sheet) {
            throw new \RuntimeException('Sheet tidak ditemukan.');
        }

        $headerRow = max(1, (int) $mapping->header_row);
        $dataStartRow = $headerRow + 1;

        // Period comes from user input; validation already enforces YYYY-MM-DD
        $periodDate = $validated['period_date'];
        if (!$periodDate || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $periodDate)) {
            return response()->json(['success' => false, 'message' => 'Tanggal periode tidak valid.'], 422);
        }
        Log::info("Strict Mode: Using Period {$periodDate} (user selected)");

        // 5. VERSIONING: Start New Run & Create Temp Table
        $existingIndexCeiling = $this->getExistingUploadIndexCeiling($baseTableName, $connection, $periodDate);
        $run = $uploadIndexService->beginRun($mapping->id, Auth::id(), $periodDate, $existingIndexCeiling);
        $uploadIndex = (int) $run->upload_index;
        $targetTableName = $baseTableName;
        if (! $useInlineIndexTable) {
            $targetTableName = $this->buildStrictVersionTableName($baseTableName, $periodDate, $uploadIndex);
        }

        $connectionInstance = DB::connection($connection);
        $connectionInstance->beginTransaction();

        try {
            // Create Version Table (only when not using inline index)
            if (! $useInlineIndexTable) {
                $uniqueKeyColumns = $mapping->columns->where('is_unique_key', true)->pluck('table_column_name')->toArray();
                $this->ensureStrictVersionTable($targetTableName, $connection, $mapping->columns, $uniqueKeyColumns);
                $this->reseedStrictVersionIdentity($baseTableName, $targetTableName, $connection);
            }

            // 6. INSERT DATA
            $columnMapping = $mapping->columns->pluck('table_column_name', 'excel_column_index')->toArray();
            $rowsToInsert = [];
            $batchSize = 1000;
            $rows = $sheet->toArray(null, true, true, true);
            $timestamp = now()->toDateTimeString();

            foreach ($rows as $rowIndex => $rowValues) {
                if ($rowIndex < $dataStartRow) continue;

                $record = [];
                $hasValue = false;
                foreach ($columnMapping as $excelCol => $dbCol) {
                    $val = $rowValues[$excelCol] ?? null;
                    if ($val !== null && $val !== '') $hasValue = true;

                    if (in_array($dbCol, ['period', 'period_date', 'periode', 'period_dt', 'perioddate'], true)) {
                        // Always override period in strict mode to the selected period to avoid accidental skips
                        $val = $periodDate;
                    }
                    $record[$dbCol] = $val;
                }

                if (!$hasValue) continue;

                $record[$periodColumnName] = $periodDate;
                $record['upload_index'] = $uploadIndex;
                $record['is_active'] = 1;
                $record['created_at'] = $timestamp;
                $record['updated_at'] = $timestamp;

                $rowsToInsert[] = $record;
            }

            if (empty($rowsToInsert)) {
                $emptyReason = "Tidak ada data yang terbaca di sheet '{$sheetName}' setelah baris header {$headerRow}.";
                $connectionInstance->rollBack();
                $uploadIndexService->failRun($run, $emptyReason);
                if (! $useInlineIndexTable) {
                    Schema::connection($connection)->dropIfExists($targetTableName);
                }

                return response()->json([
                    'success' => false,
                    'message' => $emptyReason . ' Pastikan sheet dan baris header sudah benar.'
                ], 422);
            }

            foreach (array_chunk($rowsToInsert, $batchSize) as $chunk) {
                $connectionInstance->table($targetTableName)->insert($chunk);
            }

            $connectionInstance->commit();

            // 7. ACTIVATE (Zero Downtime Switch)
            $uploadIndexService->activateRun($run);

            // Cleanup old versions later
            if ($useInlineIndexTable && $legacyIndexMeta) {
                $this->syncLegacyIndexTable($baseTableName, $connection, $uploadIndex, $periodDate);
            }
            if (! $useInlineIndexTable) {
                CleanupStrictVersions::dispatch($mapping->id, $periodDate, $baseTableName, $connection)
                    ->delay(now()->addMinutes(5));
            }

            return response()->json([
                'success' => true,
                'message' => "Upload Berhasil! Data periode {$periodDate} telah diperbarui (Versi: {$uploadIndex}).",
            ]);

        } catch (\Throwable $e) {
            $connectionInstance->rollBack();
            $uploadIndexService->failRun($run, $e->getMessage());
            if (! $useInlineIndexTable) {
                Schema::connection($connection)->dropIfExists($targetTableName);
            }
            Log::error("Strict Upload Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()], 500);
        }
    }
}
