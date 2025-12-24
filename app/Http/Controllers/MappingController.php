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
            'upload_mode' => ['nullable', 'in:upsert,strict,replace_all'],
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

            $successMessage = "Format '{$validated['name']}' berhasil didaftarkan! Tabel '{$tableName}' telah dibuat dengan " . count($validated['mappings']) . " kolom mapping dan dukungan period_date untuk versioning.";
            
            return redirect()->route('dashboard')
                ->with('success', $successMessage)
                ->with('success_details', [
                    'format_name' => $validated['name'],
                    'table_name' => $tableName,
                    'columns_count' => count($validated['mappings']),
                    'upload_mode' => $validated['upload_mode'] ?? 'not_set',
                ]);
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
                $baseVersionColumn = $this->resolveVersionIndexColumn($tableName, $connection);
                $legacyIndexMeta = $this->detectLegacyIndexTable($tableName, $connection);
                $canUseLegacyIndex = $baseVersionColumn
                    && $legacyIndexMeta
                    && strcasecmp($baseVersionColumn, $legacyIndexMeta['index_column']) === 0;
                if ($uploadIndexFilter === null && $canUseLegacyIndex) {
                    $uploadIndexFilter = $this->getActiveUploadIndexFromLegacy($tableName, $connection);
                }

                $targetVersionColumn = $targetTable === $tableName
                    ? ($baseVersionColumn ?? 'upload_index')
                    : 'upload_index';
                if (Schema::connection($connection)->hasColumn($targetTable, $targetVersionColumn) && $uploadIndexFilter !== null) {
                    if ($targetTable === $tableName) {
                        $query->where(function ($q2) use ($uploadIndexFilter, $targetVersionColumn) {
                            $q2->where($targetVersionColumn, $uploadIndexFilter)
                                ->orWhereNull($targetVersionColumn);
                        });
                    } else {
                        $query->where($targetVersionColumn, $uploadIndexFilter);
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

        $existingUpdates = collect($request->input('existing_columns', []))
            ->mapWithKeys(function ($row, $id) {
                $isUnique = isset($row['is_unique_key']) && (in_array($row['is_unique_key'], ['1', 1, true, 'true', 'on'], true));
                return [(int) $id => $isUnique];
            });

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
            'existing_columns' => ['nullable', 'array'],
            'existing_columns.*.is_unique_key' => ['nullable', 'in:0,1,true,false,on'],
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

        $reserved = ['id', 'period_date', 'upload_index', 'index_id', 'created_at', 'updated_at', 'status_batch', 'is_active', 'division_id'];
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

            // Update unique flag for existing columns (mapping metadata only)
            foreach ($mapping->columns as $col) {
                if ($existingUpdates->has($col->id)) {
                    $col->is_unique_key = $existingUpdates->get($col->id) ? 1 : 0;
                    $col->save();
                }
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

        // Detect if table has period column
        $actualTableColumns = Schema::connection($connection)->hasTable($baseTableName) 
            ? Schema::connection($connection)->getColumnListing($baseTableName)
            : [];
        $hasPeriodColumn = false;
        foreach (['period', 'period_date', 'periode', 'period_dt', 'perioddate', 'periodo', 'per_date', 'period_code', 'priod'] as $candidate) {
            if (in_array($candidate, $actualTableColumns)) {
                $hasPeriodColumn = true;
                break;
            }
        }

        $legacyIndexMeta = $this->detectLegacyIndexTable($baseTableName, $connection);
        $baseTableExists = Schema::connection($connection)->hasTable($baseTableName);
        $versionColumn = $baseTableExists ? $this->resolveVersionIndexColumn($baseTableName, $connection) : null;
        $baseHasVersionIndex = $baseTableExists && $versionColumn !== null;
        $baseVersionColumn = $versionColumn ?? 'upload_index';
        $useLegacyActivePointer = $legacyIndexMeta !== null
            && $baseHasVersionIndex
            && strcasecmp($versionColumn, $legacyIndexMeta['index_column']) === 0;
        $legacyActiveIndexQuery = $useLegacyActivePointer
            ? $this->buildLegacyActiveIndexQuery($legacyIndexMeta, $connection, $periodFilter)
            : null;

        /** @var UploadIndexService $uploadIndexService */
        $uploadIndexService = app(UploadIndexService::class);
        $activeRun = null;
        $activeRuns = collect();
        $tableName = $baseTableName;
        $legacyActiveIndex = null;
        if (! $useLegacyActivePointer) {
            if ($periodFilter) {
                $activeRun = $uploadIndexService->getActiveRun($mapping->id, $periodFilter);
                if (! $activeRun) {
                    $fallbackRun = $uploadIndexService->getActiveRun($mapping->id, null);
                    if ($fallbackRun && $fallbackRun->period_date === null) {
                        $activeRun = $fallbackRun;
                    }
                }
                if (! $activeRun) {
                    $legacyActiveIndex = $this->getActiveUploadIndexFromLegacy($baseTableName, $connection, $periodFilter);
                }
            } else {
                $activeRuns = $uploadIndexService->getActiveRuns($mapping->id);
                if ($activeRuns->isEmpty()) {
                    $legacyActiveIndex = $this->getActiveUploadIndexFromLegacy($baseTableName, $connection);
                }
            }
        }

        // Get column mapping sorted by Excel column order
        $columnMapping = $mapping->columns
            ->sortBy(fn($col) => $this->columnLetterToIndex($col->excel_column_index))
            ->pluck('table_column_name', 'excel_column_index')
            ->toArray();
        if (!empty($baseVersionColumn)) {
            $columnMapping = array_filter($columnMapping, fn($col) => $col !== $baseVersionColumn);
        }

        if (empty($columnMapping)) {
            return back()->with('error', 'Tidak ada kolom yang di-mapping untuk format ini.');
        }

        $displayColumns = array_values($columnMapping);
        $selectColumns = array_values(array_unique(array_merge(['id'], $displayColumns, ['created_at', 'updated_at'])));
        $tableColumnsCache = [];
        $connectionInstance = DB::connection($connection);
        $grammar = $connectionInstance->getQueryGrammar();

        $getTableColumns = function (string $tableName) use ($connection, &$tableColumnsCache) {
            if (!array_key_exists($tableName, $tableColumnsCache)) {
                $tableColumnsCache[$tableName] = Schema::connection($connection)->getColumnListing($tableName);
            }
            return $tableColumnsCache[$tableName];
        };

        $buildSelectColumns = function (array $actualColumns) use ($selectColumns, $grammar) {
            $selects = [];
            foreach ($selectColumns as $col) {
                if (in_array($col, $actualColumns, true)) {
                    $selects[] = $col;
                } else {
                    $selects[] = DB::raw('NULL as ' . $grammar->wrap($col));
                }
            }
            return $selects;
        };

        $buildQuery = function (
            string $tableName,
            ?int $uploadIndex = null,
            bool $includeNullUploadIndex = false,
            bool $onlyNullUploadIndex = false,
            $uploadIndexSubquery = null,
            string $versionColumn = 'upload_index'
        ) use ($connection, $user, $periodFilter, $getTableColumns, $buildSelectColumns, $baseTableName) {
            if (!Schema::connection($connection)->hasTable($tableName)) {
                return null;
            }

            $actualColumns = $getTableColumns($tableName);
            $query = DB::connection($connection)->table($tableName)->select($buildSelectColumns($actualColumns));

            if ($periodFilter) {
                $periodColumn = null;
                foreach (['period_date', 'period', 'periode', 'period_dt', 'perioddate', 'periodo', 'per_date', 'period_code'] as $candidate) {
                    if (in_array($candidate, $actualColumns, true)) {
                        $periodColumn = $candidate;
                        break;
                    }
                }
                if ($periodColumn) {
                    $query->whereDate($periodColumn, $periodFilter);
                }
            }

            if (in_array($versionColumn, $actualColumns, true)) {
                if ($uploadIndexSubquery !== null) {
                    $query->whereIn($versionColumn, $uploadIndexSubquery);
                } elseif ($onlyNullUploadIndex) {
                    $query->whereNull($versionColumn);
                } elseif ($uploadIndex !== null) {
                    if ($includeNullUploadIndex && $tableName === $baseTableName) {
                        $query->where(function ($q2) use ($uploadIndex, $versionColumn) {
                            $q2->where($versionColumn, $uploadIndex)
                                ->orWhereNull($versionColumn);
                        });
                    } else {
                        $query->where($versionColumn, $uploadIndex);
                    }
                }
            }

            if (!$this->userHasRole($user, 'superuser') && in_array('division_id', $actualColumns, true)) {
                $query->where('division_id', $user->division_id);
            }

            return $query;
        };

        $queries = [];
        $usedBaseTable = false;

        if ($useLegacyActivePointer) {
            if ($baseTableExists) {
                $queries[] = $buildQuery($baseTableName, null, false, false, $legacyActiveIndexQuery, $baseVersionColumn);
                $usedBaseTable = true;
            }
        } elseif ($periodFilter) {
            if ($activeRun && !$activeRun->period_date) {
                $queries[] = $buildQuery($baseTableName, (int) $activeRun->upload_index, false, false, null, $baseVersionColumn);
                $usedBaseTable = true;
            } elseif ($activeRun && $activeRun->period_date) {
                $candidate = $this->buildStrictVersionTableName($baseTableName, $activeRun->period_date, (int) $activeRun->upload_index);
                if (Schema::connection($connection)->hasTable($candidate)) {
                    $queries[] = $buildQuery($candidate, (int) $activeRun->upload_index, false, false, null, 'upload_index');
                } else {
                    $uploadIndexFilter = $activeRun->upload_index ?? $legacyActiveIndex;
                    $queries[] = $buildQuery($baseTableName, $uploadIndexFilter, true, false, null, $baseVersionColumn);
                    $usedBaseTable = true;
                }
            } elseif ($legacyActiveIndex !== null) {
                $queries[] = $buildQuery($baseTableName, $legacyActiveIndex, true, false, null, $baseVersionColumn);
                $usedBaseTable = true;
            } else {
                $queries[] = $buildQuery($baseTableName, null, true, false, null, $baseVersionColumn);
                $usedBaseTable = true;
            }
        } else {
            $needsUnversionedBase = false;
            if ($activeRuns->isEmpty()) {
                if ($baseTableExists) {
                    $queries[] = $buildQuery($baseTableName, $legacyActiveIndex, true, false, null, $baseVersionColumn);
                    $usedBaseTable = true;
                }
            } else {
                foreach ($activeRuns as $run) {
                    if (!$run->period_date) {
                        if ($baseTableExists) {
                            $queries[] = $buildQuery($baseTableName, (int) $run->upload_index, false, false, null, $baseVersionColumn);
                            $usedBaseTable = true;
                        }
                        continue;
                    }
                    $candidate = $this->buildStrictVersionTableName($baseTableName, $run->period_date, (int) $run->upload_index);
                    if (Schema::connection($connection)->hasTable($candidate)) {
                        $queries[] = $buildQuery($candidate, (int) $run->upload_index, false, false, null, 'upload_index');
                    } elseif ($baseTableExists) {
                        if ($baseHasVersionIndex) {
                            $queries[] = $buildQuery($baseTableName, (int) $run->upload_index, false, false, null, $baseVersionColumn);
                            $usedBaseTable = true;
                            $needsUnversionedBase = true;
                        } elseif (!$usedBaseTable) {
                            $queries[] = $buildQuery($baseTableName, null, false, false, null, $baseVersionColumn);
                            $usedBaseTable = true;
                        }
                    }
                }
                if (empty($queries) && $baseTableExists) {
                    $queries[] = $buildQuery($baseTableName, $legacyActiveIndex, true, false, null, $baseVersionColumn);
                    $usedBaseTable = true;
                }
                if ($baseTableExists && ($needsUnversionedBase || !$usedBaseTable)) {
                    $queries[] = $buildQuery($baseTableName, null, false, $baseHasVersionIndex, null, $baseVersionColumn);
                    $usedBaseTable = true;
                }
            }
        }

        $queries = array_values(array_filter($queries));
        if (empty($queries)) {
            return back()->with('error', "Tabel untuk period terpilih tidak ditemukan di koneksi {$connection}.");
        }

        $perPage = 50;
        if (count($queries) === 1) {
            $data = $queries[0]
                ->orderByDesc('id')
                ->paginate($perPage)
                ->withQueryString();
        } else {
            $union = array_shift($queries);
            foreach ($queries as $query) {
                $union->unionAll($query);
            }
            $data = DB::connection($connection)
                ->query()
                ->fromSub($union, 'combined_rows')
                ->orderByDesc('id')
                ->paginate($perPage)
                ->withQueryString();
        }

        return view('view_data', [
            'mapping' => $mapping,
            'columns' => $displayColumns,
            'data' => $data,
            'columnMapping' => $columnMapping,
            'period_date' => $activeRun->period_date ?? $periodFilter,
            'hasPeriodColumn' => $hasPeriodColumn,
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
        if (! $activeRun && $periodFilter) {
            $fallbackRun = $uploadIndexService->getActiveRun($mapping->id, null);
            if ($fallbackRun && $fallbackRun->period_date === null) {
                $activeRun = $fallbackRun;
            }
        }

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
        $versionColumn = $this->resolveVersionIndexColumn($tableName, $connection);
        $legacyIndexMeta = $this->detectLegacyIndexTable($baseTableName, $connection);
        $canUseLegacyIndex = $versionColumn
            && $legacyIndexMeta
            && strcasecmp($versionColumn, $legacyIndexMeta['index_column']) === 0;
        $legacyActiveIndex = null;
        if ($canUseLegacyIndex && ! $activeRun) {
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

        $connectionInstance->transaction(function () use ($connectionInstance, $driver, $tableName, $stringColumns, $versionColumn, $activeRun, $mapping, $legacyActiveIndex) {
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
                if ($versionColumn) {
                    $uploadIndexFilter = $activeRun->upload_index ?? $legacyActiveIndex;
                    if ($uploadIndexFilter !== null) {
                        $query->where($versionColumn, $uploadIndexFilter);
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

            $controlConnection = config('database.control_connection', env('DB_CONNECTION', config('database.default')));
            $isLegacy = function (?string $name) {
                return $name === 'sqlsrv_legacy' || ($name && str_starts_with($name, 'legacy_'));
            };
            if (! $controlConnection || $isLegacy($controlConnection)) {
                if (config('database.connections.sqlsrv')) {
                    $controlConnection = 'sqlsrv';
                } else {
                    $fallback = config('database.default');
                    $controlConnection = $isLegacy($fallback) && config('database.connections.sqlsrv') ? 'sqlsrv' : $fallback;
                }
            }

            $mapping = MappingIndex::on($controlConnection)->with('columns')->find($validated['mapping_id']);
            
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

        // Upload mode auto-recommendation based on mapping config
        $hasUniqueKey = $mappingRules->contains(function ($rule) {
            return in_array($rule->is_unique_key, [true, 1, '1', 'true'], true);
        });
        $periodCandidates = ['period', 'period_date', 'periode', 'period_dt', 'perioddate', 'periodo', 'per_date', 'period_code','priod'];
        $hasPeriodColumn = $mappingRules->contains(function ($rule) use ($periodCandidates) {
            return in_array(strtolower($rule->table_column_name), $periodCandidates, true);
        });
        $recommendedMode = $hasUniqueKey ? 'upsert' : ($hasPeriodColumn ? 'strict' : 'replace_all');
        
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
            if ($lockedMode === 'strict') {
                $lockedLabel = 'Strict (Replace by Period)';
                $lockedDesc = 'Sistem akan mengganti data per periode berdasarkan tanggal hasil mapping.';
            } elseif ($lockedMode === 'replace_all') {
                $lockedLabel = 'Replace All (Ganti Semua)';
                $lockedDesc = 'Semua data lama akan diganti sepenuhnya oleh upload terbaru tanpa downtime.';
            } else {
                $lockedLabel = 'Upsert (Update atau Insert)';
                $lockedDesc = 'Update data yang sudah ada berdasarkan kunci unik, atau insert data baru jika belum ada.';
            }

            $html .= '<div class="flex items-start p-3 bg-white rounded-lg border-2 border-dashed border-amber-300">';
            $html .= '<div class="ml-3">';
            $html .= '<div class="flex items-center">';
            $html .= '<span class="font-semibold text-gray-900">' . $lockedLabel . '</span>';
            $html .= '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">Terkunci</span>';
            $html .= '</div>';
            $html .= '<p class="text-sm text-gray-600 mt-1">' . $lockedDesc . '</p>';
            if ($lockedMode === 'strict') {
                $html .= '<p class="text-xs text-red-600 mt-1 font-medium">Perhatian: pastikan kolom tanggal di Excel sudah dimapping ke \\\'period_date\\\'!</p>';
            } elseif ($lockedMode === 'replace_all') {
                $html .= '<p class="text-xs text-amber-700 mt-1 font-medium">Perhatian: seluruh data lama akan diganti. Pastikan file berisi snapshot lengkap.</p>';
            }
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<input type="hidden" name="upload_mode" value="' . htmlspecialchars($lockedMode, ENT_QUOTES, 'UTF-8') . '">';
        } else {
            $html .= '<div class="space-y-3">';
            
            // Upsert mode
            $html .= '<label class="flex items-start p-3 bg-white rounded-lg border-2 border-green-200 cursor-pointer hover:border-green-400 transition-all duration-200">';
            $html .= '<input type="radio" name="upload_mode" value="upsert" ' . ($recommendedMode === 'upsert' ? 'checked' : '') . ' class="mt-1 rounded-full border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">';
            $html .= '<div class="ml-3">';
            $html .= '<div class="flex items-center">';
            $html .= '<span class="font-semibold text-gray-900">Upsert (Update atau Insert)</span>';
            if ($recommendedMode === 'upsert') {
                $html .= '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Rekomendasi</span>';
            }
            $html .= '</div>';
            $html .= '<p class="text-sm text-gray-600 mt-1">Update data yang sudah ada berdasarkan kunci unik, atau insert data baru jika belum ada</p>';
            $html .= '</div>';
            $html .= '</label>';
            
            // Strict mode
            $html .= '<label class="flex items-start p-3 bg-white rounded-lg border-2 border-red-200 cursor-pointer hover:border-red-400 transition-all duration-200">';
            $html .= '<input type="radio" name="upload_mode" value="strict" ' . ($recommendedMode === 'strict' ? 'checked' : '') . ' class="mt-1 rounded-full border-gray-300 text-red-600 shadow-sm focus:border-red-300 focus:ring focus:ring-red-200 focus:ring-opacity-50">';
            $html .= '<div class="ml-3">';
            $html .= '<div class="flex items-center">';
            $html .= '<span class="font-semibold text-gray-900">Strict (Replace by Period)</span>';
            $html .= '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">' . ($recommendedMode === 'strict' ? 'Rekomendasi' : 'Hati-hati') . '</span>';
            $html .= '</div>';
            $html .= '<p class="text-sm text-gray-600 mt-1">Sistem akan <strong>otomatis mendeteksi tanggal</strong> dari file Excel (berdasarkan mapping), menghapus data lama pada bulan tersebut, lalu memasukkan data baru.</p>';
            $html .= '<p class="text-xs text-red-600 mt-1 font-medium">Perhatian: pastikan kolom tanggal di Excel sudah dimapping ke "period" </p>';
            $html .= '</div>';
            $html .= '</label>';

            // Replace All mode
            $html .= '<label class="flex items-start p-3 bg-white rounded-lg border-2 border-indigo-200 cursor-pointer hover:border-indigo-400 transition-all duration-200">';
            $html .= '<input type="radio" name="upload_mode" value="replace_all" ' . ($recommendedMode === 'replace_all' ? 'checked' : '') . ' class="mt-1 rounded-full border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">';
            $html .= '<div class="ml-3">';
            $html .= '<div class="flex items-center">';
            $html .= '<span class="font-semibold text-gray-900">Replace All (Ganti Semua)</span>';
            $html .= '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">' . ($recommendedMode === 'replace_all' ? 'Rekomendasi' : 'Snapshot') . '</span>';
            $html .= '</div>';
            $html .= '<p class="text-sm text-gray-600 mt-1">Seluruh data lama diganti oleh upload terbaru menggunakan <strong>index_id</strong> tanpa downtime.</p>';
            $html .= '<p class="text-xs text-amber-700 mt-1 font-medium">Pastikan file berisi snapshot lengkap agar tidak ada data tertinggal.</p>';
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
        $uniqueColumns = $mappingRules->filter(function ($rule) {
            return in_array($rule->is_unique_key, [true, 1, '1', 'true'], true);
        })->pluck('table_column_name', 'excel_column_index')->toArray();
        
        foreach ($headers as $index => $header) {
            $excelCol = $this->indexToColumn($index);
            $mappedDbCol = $dbColumns[$excelCol] ?? '';
            $isUnique = isset($uniqueColumns[$excelCol]);
            
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

            // Unique key toggle
            $html .= '<div class="mt-2 inline-flex items-center space-x-2 bg-amber-50 border border-amber-200 rounded px-2.5 py-1.5 w-fit">';
            $html .= '<input type="checkbox" class="unique-checkbox rounded border-amber-300 text-amber-600 shadow-sm focus:border-amber-300 focus:ring focus:ring-amber-200 focus:ring-opacity-50" data-excel-col="' . $excelCol . '" ' . ($isUnique ? 'checked' : '') . '>';
            $html .= '<span class="text-xs font-semibold text-amber-800">Jadikan Kunci Unik</span>';
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
     * Ensure version index column exists on the target table (upload_index or index_id).
     */
    private function ensureUploadIndexColumn(string $tableName, string $connection, string $columnName = 'upload_index'): void
    {
        $schema = Schema::connection($connection);
        if (! $schema->hasColumn($tableName, $columnName)) {
            $schema->table($tableName, function (Blueprint $table) use ($columnName) {
                $table->integer($columnName)->nullable()->index();
            });
            Log::info("Added {$columnName} column on {$tableName} (connection: {$connection}) for dataset versioning");
        }
    }

    /**
     * Create legacy *_INDEX table if missing, return its meta.
     */
    private function ensureLegacyIndexTable(string $baseTable, string $connection): ?array
    {
        $meta = $this->detectLegacyIndexTable($baseTable, $connection);
        if ($meta) {
            return $meta;
        }

        $indexTable = $baseTable . '_INDEX';
        try {
            Schema::connection($connection)->create($indexTable, function (Blueprint $table) {
                // Use proper auto-increment bigInteger primary key
                $table->bigIncrements('index_id');
                $table->date('period_date')->nullable()->index();
                $table->string('status_batch', 20)->default('inactive')->index();
                $table->timestamps();
            });
            Log::info("Legacy index table created", [
                'table' => $indexTable,
                'connection' => $connection,
            ]);
            
            // After creation, detect and return metadata
            return $this->detectLegacyIndexTable($baseTable, $connection);
        } catch (\Throwable $e) {
            Log::warning('Gagal membuat legacy index table', [
                'table' => $indexTable,
                'connection' => $connection,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Drop data rows for inactive index_ids after a successful Replace All switch.
     */
    private function cleanupInactiveIndexData(string $baseTable, string $connection, string $versionColumn, array $meta, int $activeIndex): void
    {
        try {
            if (!Schema::connection($connection)->hasTable($baseTable) || !Schema::connection($connection)->hasColumn($baseTable, $versionColumn)) {
                return;
            }

            $conn = DB::connection($connection);
            $grammar = $conn->getQueryGrammar();
            $statusMode = $meta['status_mode'] ?? 'batch';

            $inactiveIds = $conn->table($meta['table'])
                ->where($meta['index_column'], '<>', $activeIndex)
                ->where(function ($q) use ($meta, $grammar, $statusMode) {
                    $wrapped = $grammar->wrap($meta['status_column']);
                    if ($statusMode === 'batch') {
                        $q->whereRaw("UPPER({$wrapped}) = 'INACTIVE'");
                    } else {
                        $q->where($meta['status_column'], 0)
                            ->orWhere($meta['status_column'], false);
                    }
                })
                ->pluck($meta['index_column'])
                ->filter(fn($v) => is_numeric($v))
                ->map(fn($v) => (int) $v)
                ->values();

            if ($inactiveIds->isEmpty()) {
                return;
            }

            $deleted = DB::connection($connection)
                ->table($baseTable)
                ->whereIn($versionColumn, $inactiveIds)
                ->delete();

            Log::info('Cleanup inactive index data completed', [
                'table' => $baseTable,
                'connection' => $connection,
                'version_column' => $versionColumn,
                'inactive_ids' => $inactiveIds->all(),
                'rows_deleted' => $deleted,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Cleanup inactive index data skipped', [
                'table' => $baseTable,
                'connection' => $connection,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve which version index column exists on a table (index_id or upload_index).
     */
    private function resolveVersionIndexColumn(string $tableName, string $connection): ?string
    {
        $schema = Schema::connection($connection);
        if ($schema->hasColumn($tableName, 'index_id')) {
            return 'index_id';
        }
        if ($schema->hasColumn($tableName, 'upload_index')) {
            return 'upload_index';
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
        if (!$indexColumn || !$statusColumn) {
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

    /**
     * Build a subquery for active index_id from legacy *_INDEX (optionally filtered by period).
     */
    private function buildLegacyActiveIndexQuery(array $meta, string $connection, ?string $periodDate = null)
    {
        $conn = DB::connection($connection);
        $grammar = $conn->getQueryGrammar();

        $query = $conn->table($meta['table'])
            ->select($meta['index_column'])
            ->where(function ($q) use ($meta, $grammar) {
                $wrapped = $grammar->wrap($meta['status_column']);
                if (($meta['status_mode'] ?? 'batch') === 'batch') {
                    $q->whereRaw("UPPER({$wrapped}) = 'ACTIVE'");
                } else {
                    $q->where($meta['status_column'], 1)
                        ->orWhere($meta['status_column'], true)
                        ->orWhereRaw("LOWER({$wrapped}) = 'active'");
                }
            });

        if ($periodDate && $meta['period_column']) {
            $query->whereDate($meta['period_column'], $periodDate);
        }

        return $query;
    }

    /**
     * Get the currently active index_id from legacy *_INDEX table when present.
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
                    $wrapped = $grammar->wrap($meta['status_column']);
                    if (($meta['status_mode'] ?? 'batch') === 'batch') {
                        $q->whereRaw("UPPER({$wrapped}) = 'ACTIVE'");
                    } else {
                        $q->where($meta['status_column'], 1)
                            ->orWhere($meta['status_column'], true)
                            ->orWhereRaw("LOWER({$wrapped}) = 'active'");
                    }
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
     * Ambil nilai maksimum index dari tabel utama dan/atau tabel legacy *_INDEX.
     */
    private function getExistingUploadIndexCeiling(string $baseTable, string $connection, ?string $periodDate = null): ?int
    {
        $max = null;

        if (Schema::connection($connection)->hasTable($baseTable)) {
            $indexColumn = $this->resolveVersionIndexColumn($baseTable, $connection);
            if ($indexColumn) {
                $columns = Schema::connection($connection)->getColumnListing($baseTable);
                $query = DB::connection($connection)->table($baseTable);
                if ($periodDate && in_array('period_date', $columns, true)) {
                    $query->whereDate('period_date', $periodDate);
                }
                $val = $query->max($indexColumn);
                if (is_numeric($val)) {
                    $max = (int) $val;
                }
            }
        }

        $legacyMeta = $this->detectLegacyIndexTable($baseTable, $connection);
        if ($legacyMeta) {
            $conn = DB::connection($connection);

            // Prefer the latest ACTIVE index_id to continue the sequence cleanly
            $activeQuery = $this->buildLegacyActiveIndexQuery($legacyMeta, $connection, $periodDate);
            $activeVal = $activeQuery->max($legacyMeta['index_column']);
            if (is_numeric($activeVal)) {
                $max = $max === null ? (int) $activeVal : max($max, (int) $activeVal);
            }

            // Fallback: absolute max regardless of status
            $query = $conn->table($legacyMeta['table']);
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
     * Create a legacy *_INDEX row in PROCESSING state and return the new index_id.
     * Smart allocation: continues from existing max index.
     */
    private function createLegacyIndexBatch(string $baseTable, string $connection, ?string $periodDate = null, bool $createIfMissing = false): ?int
    {
        $meta = $this->detectLegacyIndexTable($baseTable, $connection);
        if (! $meta && $createIfMissing) {
            $meta = $this->ensureLegacyIndexTable($baseTable, $connection);
        }
        if (!$meta) {
            Log::warning('No legacy index table metadata found', [
                'base_table' => $baseTable,
                'connection' => $connection,
            ]);
            return null;
        }

        try {
            $conn = DB::connection($connection);
            
            // Get max ceiling from existing data
            $maxCeiling = $this->getExistingUploadIndexCeiling($baseTable, $connection, $periodDate);
            $activeCeiling = $this->getActiveUploadIndexFromLegacy($baseTable, $connection, $periodDate);
            if (is_numeric($activeCeiling)) {
                $maxCeiling = max($maxCeiling ?? 0, (int) $activeCeiling);
            }
            
            // Also check control connection's mapping_upload_runs for same mapping
            $controlConnection = config('database.control_connection', config('database.default'));
            $controlConn = DB::connection($controlConnection);
            $mappingId = $controlConn->table('mapping_indices')
                ->where('table_name', $baseTable)
                ->first()?->id;
            
            if ($mappingId) {
                $controlMax = $controlConn->table('mapping_upload_runs')
                    ->where('mapping_index_id', $mappingId)
                    ->when(
                        $periodDate !== null,
                        fn($q) => $q->where('period_date', $periodDate),
                        fn($q) => $q->whereNull('period_date')
                    )
                    ->max('upload_index');
                
                $maxCeiling = max($maxCeiling ?? 0, (int) ($controlMax ?? 0));
            }
            
            $now = now();
            $statusMode = $meta['status_mode'] ?? 'batch';
            $payload = [
                $meta['status_column'] => $statusMode === 'batch' ? 'processing' : 0,
            ];
            if ($meta['period_column']) {
                $payload[$meta['period_column']] = $periodDate;
            }
            if ($meta['has_updated_at']) {
                $payload['updated_at'] = $now;
            }
            if ($meta['has_created_at']) {
                $payload['created_at'] = $now;
            }

            // Explicitly set next index to continue from ceiling
            $nextIndex = null;
            if ($maxCeiling && $maxCeiling > 0) {
                $nextIndex = $maxCeiling + 1;
                $payload[$meta['index_column']] = $nextIndex;
            }
            
            Log::info('Inserting into legacy index table', [
                'table' => $meta['table'],
                'index_column' => $meta['index_column'],
                'payload' => $payload,
                'max_ceiling' => $maxCeiling,
                'next_index' => $nextIndex,
            ]);
            
            // When explicitly setting the index column value, we need to insert then query back the ID
            if ($nextIndex !== null) {
                // Insert with explicit value
                $conn->table($meta['table'])->insert($payload);
                // Query back to get the inserted row's ID
                $query = $conn->table($meta['table'])
                    ->where($meta['index_column'], $nextIndex);
                if ($meta['period_column'] && $periodDate) {
                    $query->where($meta['period_column'], $periodDate);
                }
                $newId = $query->value('index_id') ?? $nextIndex;
            } else {
                // Let the database auto-generate the index_id
                $newId = $conn->table($meta['table'])->insertGetId($payload);
            }
            
            Log::info('Legacy index batch created (smart allocation)', [
                'base_table' => $baseTable,
                'new_index_id' => $newId,
                'max_ceiling' => $maxCeiling,
                'period_date' => $periodDate,
                'is_numeric' => is_numeric($newId),
                'type' => gettype($newId),
            ]);
            
            if (!is_numeric($newId) || $newId === '' || $newId === null) {
                Log::error('insertGetId/insert returned invalid value', [
                    'newId' => var_export($newId, true),
                    'table' => $meta['table'],
                    'payload' => $payload,
                ]);
                return null;
            }
            
            return (int) $newId;
        } catch (\Throwable $e) {
            Log::warning('Gagal membuat legacy index batch', [
                'base_table' => $baseTable,
                'index_table' => $meta['table'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return null;
    }

    /**
     * Sync legacy *_INDEX table to mark the provided index as active.
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

            $statusMode = $meta['status_mode'] ?? 'batch';
            $inactiveValue = $statusMode === 'batch' ? 'inactive' : 0;
            $activeValue = $statusMode === 'batch' ? 'active' : 1;

            // Deactivate previous entries
            $inactivePayload = [$meta['status_column'] => $inactiveValue];
            if ($meta['has_updated_at']) {
                $inactivePayload['updated_at'] = $now;
            }
            $deactivateQuery = $conn->table($meta['table']);
            if ($meta['period_column'] && $periodDate) {
                $deactivateQuery->whereDate($meta['period_column'], $periodDate);
            }
            $deactivateQuery->update($inactivePayload);

            // Upsert the new active index
            $activePayload = [
                $meta['index_column'] => $uploadIndex,
                $meta['status_column'] => $activeValue,
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

            $existing = $conn->table($meta['table'])
                ->where($meta['index_column'], $uploadIndex);
            if ($meta['period_column'] && $periodDate) {
                $existing->whereDate($meta['period_column'], $periodDate);
            }

            if ($existing->exists()) {
                $existing->update($activePayload);
            } else {
                $conn->table($meta['table'])->insert($activePayload);
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
            'upload_mode' => ['required', 'string', Rule::in(['upsert', 'strict', 'replace_all'])],
            'sheet_name' => ['nullable', 'string'],
            'period_date' => ['nullable', 'required_if:upload_mode,strict', 'date_format:Y-m-d'],
        ], [
            'period_date.date_format' => 'Period harus menggunakan format YYYY-MM-DD.',
        ]);

        $mapping = MappingIndex::with('columns')->find($validated['mapping_id']);
        if (! $mapping) {
            return response()->json([
                'success' => false,
                'message' => 'Format tidak ditemukan.',
            ], 404);
        }

        $connection = $mapping->target_connection
            ?? $mapping->connection
            ?? config('database.default');
        $this->ensureLegacyConnectionConfigured($connection);

        $mainTable = $mapping->table_name;
        if (! Schema::connection($connection)->hasTable($mainTable)) {
            return response()->json([
                'success' => false,
                'message' => "Tabel '{$mainTable}' tidak ditemukan.",
            ], 404);
        }

        $hasPeriodDate = Schema::connection($connection)->hasColumn($mainTable, 'period_date');

        // Hitung kolom unik yang dipilih (jika ada filter kolom)
        $mappingRules = $mapping->columns;
        if (!empty($validated['selected_columns'])) {
            $selectedColumns = json_decode($validated['selected_columns'], true);
            if (is_array($selectedColumns)) {
                $selectedValues = array_filter($selectedColumns);
                $mappingRules = $mappingRules->filter(function ($rule) use ($selectedColumns, $selectedValues) {
                    $excelKeyMatch = isset($selectedColumns[$rule->excel_column_index]) && !empty($selectedColumns[$rule->excel_column_index]);
                    $valueMatch = in_array($rule->table_column_name, $selectedValues, true);
                    return $excelKeyMatch || $valueMatch;
                });
            }
        }
        $uniqueKeyColumns = $mappingRules->where('is_unique_key', true)->pluck('table_column_name')->toArray();

        $effectiveMode = $validated['upload_mode'];
        if ($effectiveMode === 'upsert' && empty($uniqueKeyColumns) && $hasPeriodDate) {
            if (empty($validated['period_date'])) {
                return response()->json([
                    'success' => false,
                    'require_period' => true,
                    'forced_mode' => 'strict',
                    'message' => 'Mode upsert butuh kunci unik. Karena tabel ada period_date, isi period dan jalankan seperti strict (drop data di period itu).',
                ], 422);
            }
            $effectiveMode = 'strict';
        }

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
            'upload_mode' => $effectiveMode,
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
        $controlConnection = config('database.control_connection', config('database.default'));
        $isLegacyControl = function (?string $name) {
            return $name === 'sqlsrv_legacy' || ($name && str_starts_with($name, 'legacy_'));
        };
        if (! $controlConnection || $isLegacyControl($controlConnection)) {
            if (config('database.connections.sqlsrv')) {
                $controlConnection = 'sqlsrv';
            } else {
                $fallback = config('database.default');
                $controlConnection = $isLegacyControl($fallback) && config('database.connections.sqlsrv') ? 'sqlsrv' : $fallback;
            }
        }
        $originalDefaultConnection = DB::getDefaultConnection();
        DB::setDefaultConnection($controlConnection);
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
                'upload_mode' => ['required', 'string', Rule::in(['upsert', 'strict', 'replace_all'])],
                'sheet_name' => ['nullable', 'string'],
                'period_date' => ['nullable', 'required_if:upload_mode,strict', 'date_format:Y-m-d'],
            ], [
                'period_date.date_format' => 'Period harus menggunakan format YYYY-MM-DD.',
            ]);
            
            $uploadMode = $validated['upload_mode'];
            // Use provided period_date (strict) or today's date for table columns
            $periodDate = $validated['period_date'] ?? now()->toDateString();
            $runPeriodDate = $uploadMode === 'strict' ? $periodDate : null;
            // Use ISO 8601 (T separator) for datetime to keep SQL Server bulk insert happy
            $nowString = now()->format('Y-m-d\\TH:i:s.v');
            // Remove delimiter/newlines that could break bulk parsing
            $nowStringSanitized = str_replace(['|', "\r", "\n"], ' ', $nowString);
            Log::info('Menggunakan period_date', [
                'period_date' => $periodDate,
                'upload_mode' => $uploadMode,
                'run_period_date' => $runPeriodDate,
            ]);

            $controlConnection = config('database.control_connection', env('DB_CONNECTION', config('database.default')));
            $isLegacyControl = function (?string $name) {
                return $name === 'sqlsrv_legacy' || ($name && str_starts_with($name, 'legacy_'));
            };
            if (! $controlConnection || $isLegacyControl($controlConnection)) {
                if (config('database.connections.sqlsrv')) {
                    $controlConnection = 'sqlsrv';
                } else {
                    $fallback = config('database.default');
                    $controlConnection = $isLegacyControl($fallback) && config('database.connections.sqlsrv') ? 'sqlsrv' : $fallback;
                }
            }

            $mapping = MappingIndex::on($controlConnection)->with('columns')->find($validated['mapping_id']);
            
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
            $selectedUniqueKeys = null;
            if (!empty($validated['selected_columns'])) {
                $selectedColumns = json_decode($validated['selected_columns'], true);
                Log::info('Selected columns:', $selectedColumns);
            }
            if (!empty($request->input('selected_unique_keys'))) {
                $selectedUniqueKeys = json_decode($request->input('selected_unique_keys'), true);
                if (! is_array($selectedUniqueKeys)) {
                    $selectedUniqueKeys = null;
                }
                Log::info('Selected unique keys:', $selectedUniqueKeys ?? []);
            }

            // Get mapping rules and filter by selected columns
            $mappingRules = $mapping->columns;
            
            if ($selectedColumns !== null && is_array($selectedColumns)) {
                $selectedValues = array_filter($selectedColumns);
                $mappingRules = $mappingRules->filter(function ($rule) use ($selectedColumns, $selectedValues) {
                    $excelKeyMatch = isset($selectedColumns[$rule->excel_column_index]) && !empty($selectedColumns[$rule->excel_column_index]);
                    $valueMatch = in_array($rule->table_column_name, $selectedValues, true);
                    return $excelKeyMatch || $valueMatch;
                });
                Log::info('Filtered mapping rules count: ' . $mappingRules->count());
            }
            if ($selectedUniqueKeys !== null && is_array($selectedUniqueKeys)) {
                $mappingRules = $mappingRules->map(function ($rule) use ($selectedUniqueKeys) {
                    if (isset($selectedUniqueKeys[$rule->excel_column_index])) {
                        $rule->is_unique_key = true;
                    }
                    return $rule;
                });
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
            // - If table has period_date, force strict and require period
            // - Otherwise append to avoid unintended replace
            if ($uploadMode === 'upsert' && empty($uniqueKeyColumns)) {
                if ($hasPeriodDate) {
                    if (empty($validated['period_date'])) {
                        return response()->json([
                            'success' => false,
                            'require_period' => true,
                            'forced_mode' => 'strict',
                            'message' => 'Mode upsert butuh kunci unik. Karena tabel ada period_date, isi period dan jalankan seperti strict (drop data di period itu).',
                        ], 422);
                    }
                    Log::warning('Upsert mode selected with period_date but no unique keys. Forcing strict with period.', [
                        'mapping_id' => $mapping->id,
                        'table' => $mainTableName,
                        'connection' => $connection,
                    ]);
                    $uploadMode = 'strict';
                    $runPeriodDate = $validated['period_date'];
                } else {
                    Log::warning('Upsert mode selected but no unique keys defined. Switching to append mode to avoid data replacement.', [
                        'mapping_id' => $mapping->id,
                        'table' => $mainTableName,
                        'connection' => $connection,
                    ]);
                    $uploadMode = 'append';
                }
            }
            $useUploadIndex = in_array($uploadMode, ['strict', 'replace_all'], true);
            $isReplaceAll = $uploadMode === 'replace_all';
            $legacyIndexMeta = $useUploadIndex ? $this->detectLegacyIndexTable($mainTableName, $connection) : null;
            $useInlineIndexTable = $useUploadIndex
                && ($isReplaceAll || $legacyIndexMeta !== null || $this->isLegacyConnectionName($connection));
            $versionColumn = $useUploadIndex ? 'upload_index' : null;
            if ($useUploadIndex && $useInlineIndexTable) {
                $versionColumn = $legacyIndexMeta['index_column']
                    ?? $this->resolveVersionIndexColumn($mainTableName, $connection)
                    ?? ($this->isLegacyConnectionName($connection) ? 'index_id' : 'upload_index');
            }

            if ($useUploadIndex) {
                if ($uploadMode === 'strict' && empty($validated['period_date'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Period date wajib diisi untuk mode strict.'
                    ], 422);
                }

                $this->ensureUploadIndexColumn($mainTableName, $connection, $versionColumn ?? 'upload_index');

                // Ensure legacy index table if needed, then re-detect metadata
                if ($isReplaceAll && $useInlineIndexTable && ! $legacyIndexMeta) {
                    $legacyIndexMeta = $this->ensureLegacyIndexTable($mainTableName, $connection);
                }

                // NOW re-evaluate $useLegacyIndexId AFTER ensuring the table exists
                $useLegacyIndexId = $useUploadIndex
                    && $isReplaceAll
                    && $useInlineIndexTable
                    && $legacyIndexMeta !== null
                    && ($legacyIndexMeta['status_mode'] ?? 'batch') === 'batch';

                // For replace_all mode with legacy index, keep existing rows and just append the next index.
                // We still log the currently active index for visibility/debugging.
                if ($useLegacyIndexId && $legacyIndexMeta) {
                    try {
                        $activeIndex = $this->buildLegacyActiveIndexQuery($legacyIndexMeta, $connection, $runPeriodDate)
                            ->max($legacyIndexMeta['index_column']);
                        Log::info('Legacy index table preserved (no truncate). Latest active index detected.', [
                            'table' => $legacyIndexMeta['table'],
                            'connection' => $connection,
                            'active_index' => $activeIndex,
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('Unable to read active legacy index (table preserved)', [
                            'table' => $legacyIndexMeta['table'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                
                $existingIndexCeiling = $useUploadIndex && ! $useLegacyIndexId
                    ? $this->getExistingUploadIndexCeiling($mainTableName, $connection, $runPeriodDate)
                    : null;

                if ($useLegacyIndexId) {
                    $legacyIndexId = $this->createLegacyIndexBatch($mainTableName, $connection, null, true);
                    if (! $legacyIndexId) {
                        throw new \RuntimeException('Gagal membuat index batch legacy.');
                    }
                    $uploadRun = $uploadIndexService->beginRunWithIndex($mapping->id, $legacyIndexId, Auth::id(), $runPeriodDate);
                    $uploadIndexValue = (int) $legacyIndexId;
                } else {
                    $uploadRun = $uploadIndexService->beginRun($mapping->id, Auth::id(), $runPeriodDate, $existingIndexCeiling);
                    $uploadIndexValue = (int) $uploadRun->upload_index;
                }

                if ($legacyIndexMeta) {
                    Log::info('Legacy index table detected for versioning', [
                        'upload_mode' => $uploadMode,
                        'index_table' => $legacyIndexMeta['table'],
                        'index_column' => $legacyIndexMeta['index_column'],
                        'status_column' => $legacyIndexMeta['status_column'],
                        'status_mode' => $legacyIndexMeta['status_mode'],
                        'ceiling' => $existingIndexCeiling,
                        'next_index_id' => $uploadIndexValue,
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
                Log::info('Using inline versioning on base table', [
                    'table' => $mainTableName,
                    'index_column' => $versionColumn ?? 'upload_index',
                    'index_value' => $uploadIndexValue,
                    'upload_mode' => $uploadMode,
                ]);
            }
            if ($uploadMode === 'strict' && $useUploadIndex && ! $useInlineIndexTable) {
                $hasPeriodDate = true;
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
            $versionColumnInMapping = $useUploadIndex && $versionColumn && in_array($versionColumn, $orderedColumns, true);
            $stagingColumns = array_merge(
                $orderedColumns,
                $hasPeriodDate ? ['period_date'] : [],
                ($useUploadIndex && $versionColumn && ! $versionColumnInMapping) ? [$versionColumn] : [],
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
            Schema::connection($connection)->create($stagingTableName, function (Blueprint $table) use ($columnMapping, $uniqueKeyColumns, $driver, $uploadMode, $hasPeriodDate, $useUploadIndex, $versionColumn, $versionColumnInMapping) {
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
                if ($useUploadIndex && $versionColumn && ! $versionColumnInMapping) {
                    $table->integer($versionColumn)->nullable()->index();
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
                    if ($useUploadIndex && $versionColumn && $dbColumn === $versionColumn) {
                        $cellValue = $uploadIndexValue;
                    }

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
                if ($useUploadIndex && ! $versionColumnInMapping) {
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
                $targetColumns = $schema->getColumnListing($targetTableName);
                $targetColumnMap = array_fill_keys(array_map('strtolower', $targetColumns), true);
                $allowTimestamps = ! $this->isLegacyConnectionName($connection);
                $hasCreatedAt = $allowTimestamps && isset($targetColumnMap['created_at']);
                $hasUpdatedAt = $allowTimestamps && isset($targetColumnMap['updated_at']);
                if ($uploadMode === 'append') {
                    // APPEND MODE: Insert all staging rows as-is (with safe conversions)
                    Log::info("APPEND MODE: Inserting data without deletion for table '{$targetTableName}'");

                    $columns = array_values($columnMapping);
                    if ($hasPeriodDate) {
                        $columns[] = 'period_date';
                    }
                    if ($useUploadIndex && $versionColumn && !in_array($versionColumn, $columns, true)) {
                        $columns[] = $versionColumn;
                    }
                    if ($hasCreatedAt) {
                        $columns[] = 'created_at';
                    }
                    if ($hasUpdatedAt) {
                        $columns[] = 'updated_at';
                    }
                    $columns = array_values(array_filter($columns, fn($col) => isset($targetColumnMap[strtolower($col)])));

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

                } elseif (in_array($uploadMode, ['strict', 'replace_all'], true)) {
                    if ($useUploadIndex && $uploadIndexValue === null) {
                        throw new \RuntimeException('index run belum diinisialisasi.');
                    }
                    $modeLabel = $uploadMode === 'replace_all' ? 'REPLACE ALL' : 'STRICT';
                    // Version index mode: keep previous versions, insert new dataset with version index
                    $versionLabel = $versionColumn ?? 'upload_index';
                    Log::info("{$modeLabel} MODE: inserting dataset {$versionLabel} {$uploadIndexValue} for table '{$targetTableName}'");

                    $columns = array_merge(
                        array_values($columnMapping),
                        $hasPeriodDate ? ['period_date'] : [],
                        $useUploadIndex && $versionColumn && !in_array($versionColumn, array_values($columnMapping), true) ? [$versionColumn] : []
                    );
                    if ($hasCreatedAt) {
                        $columns[] = 'created_at';
                    }
                    if ($hasUpdatedAt) {
                        $columns[] = 'updated_at';
                    }
                    if ($useUploadIndex && $versionColumn && in_array($versionColumn, array_values($columnMapping), true)) {
                        $columns = array_values(array_unique($columns));
                    }
                    $columns = array_values(array_filter($columns, fn($col) => isset($targetColumnMap[strtolower($col)])));

                    // Ensure we don't duplicate the same index value if a retry happens
                    DB::table($targetTableName)
                        ->where($versionColumn ?? 'upload_index', $uploadIndexValue)
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

                    if ($uploadMode === 'replace_all') {
                        Log::info("REPLACE ALL: Successfully inserted dataset ({$versionLabel} {$uploadIndexValue})");
                        $message = $totalRows . " baris data berhasil diimpor (Replace All, {$versionLabel} {$uploadIndexValue}).";
                    } else {
                        Log::info("STRICT MODE: Successfully inserted versioned dataset ({$versionLabel} {$uploadIndexValue})");
                        $message = $totalRows . " baris data berhasil diimpor (Strict, {$versionLabel} {$uploadIndexValue}).";
                    }

                    $this->updateUploadRunProgress($uploadRun?->id, 85);

                } else {
                    // UPSERT MODE
                    $dataColumns = array_values(array_filter($columnMapping, fn($col) => isset($targetColumnMap[strtolower($col)])));
                    $allColumns = $dataColumns;
                    if ($hasPeriodDate && isset($targetColumnMap['period_date'])) {
                        $allColumns[] = 'period_date';
                    }
                    if ($hasCreatedAt) {
                        $allColumns[] = 'created_at';
                    }
                    if ($hasUpdatedAt) {
                        $allColumns[] = 'updated_at';
                    }
                    
                    if ($driver === 'sqlsrv') {
                        // SQL Server uses MERGE for upsert
                    Log::info("UPSERT MODE: Using MERGE for unique keys: " . implode(', ', $uniqueKeyColumns));
                        
                        $onClause = implode(' AND ', array_map(fn($col) => "target.[{$col}] = source.[{$col}]", $uniqueKeyColumns));
                        $updateSetParts = array_map(fn($col) => "target.[{$col}] = source.[{$col}]", $dataColumns);
                        if (in_array('updated_at', $allColumns, true)) {
                            $updateSetParts[] = "target.[updated_at] = source.[updated_at]";
                        }
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
                        if ($hasUpdatedAt) {
                            $updateClauses[] = "\"updated_at\" = EXCLUDED.\"updated_at\"";
                        }
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

                // Switch back to system/control connection before touching system tables
                DB::setDefaultConnection($controlConnection);
                
                if ($uploadRun && $useUploadIndex) {
                    $activationScope = $isReplaceAll ? 'all' : 'period';
                    $uploadIndexService->activateRun($uploadRun, $activationScope);

                    if ($isReplaceAll) {
                        $uploadIndexService->switchActiveIndex($mapping->id, $uploadIndexValue);
                        if ($legacyIndexMeta && $versionColumn) {
                            $this->cleanupInactiveIndexData($mainTableName, $connection, $versionColumn, $legacyIndexMeta, $uploadIndexValue);
                        }
                    } elseif ($legacyIndexMeta) {
                        $this->syncLegacyIndexTable(
                            $mainTableName,
                            $connection,
                            $uploadIndexValue,
                            $periodDate
                        );
                    }

                    if ($uploadMode === 'strict' && ! $useInlineIndexTable) {
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
                // Ensure default connection is restored before rethrow
                DB::setDefaultConnection($controlConnection);
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
        } finally {
            DB::setDefaultConnection($originalDefaultConnection);
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

        $controlConnection = config('database.control_connection', config('database.default'));
        $isLegacy = fn($name) => $name === 'sqlsrv_legacy' || ($name && str_starts_with($name, 'legacy_'));
        if (! $controlConnection || $isLegacy($controlConnection)) {
            $controlConnection = config('database.connections.sqlsrv') ? 'sqlsrv' : config('database.default');
        }

        $mapping = MappingIndex::on($controlConnection)->find($validated['mapping_id']);
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
        $useInlineIndexTable = $legacyIndexMeta !== null || $this->isLegacyConnectionName($connection);
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
            $schema = Schema::connection($connection);
            $targetColumns = $schema->getColumnListing($targetTableName);
            $targetColumnMap = array_fill_keys($targetColumns, true);
            $targetColumnTypes = [];
            foreach ($targetColumns as $col) {
                try {
                    $targetColumnTypes[$col] = $schema->getColumnType($targetTableName, $col);
                } catch (\Throwable $e) {
                    $targetColumnTypes[$col] = null;
                }
            }
            $numericTypes = ['integer', 'bigint', 'smallint', 'tinyint', 'decimal', 'float', 'double'];
            $dateTypes = ['date', 'datetime', 'datetimetz', 'timestamp'];
            $normalizeNumeric = function ($value, string $type) {
                if ($value === null || $value === '') {
                    return null;
                }
                if (is_string($value)) {
                    $value = trim($value);
                    if ($value === '') {
                        return null;
                    }
                    $hasDot = str_contains($value, '.');
                    $hasComma = str_contains($value, ',');
                    if ($hasComma && !$hasDot) {
                        $value = str_replace('.', '', $value);
                        $value = str_replace(',', '.', $value);
                    } elseif ($hasComma) {
                        $value = str_replace(',', '', $value);
                    }
                }
                if (!is_numeric($value)) {
                    return null;
                }
                if (in_array($type, ['integer', 'bigint', 'smallint', 'tinyint'], true)) {
                    return (int) $value;
                }
                return (float) $value;
            };
            $normalizeDate = function ($value) {
                if ($value === null || $value === '') {
                    return null;
                }
                $converted = $this->convertExcelDate($value);
                if (is_string($converted) && preg_match('/^\\d{4}-\\d{2}-\\d{2}/', $converted)) {
                    return $converted;
                }
                return null;
            };
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
                    if (is_string($val)) {
                        $val = trim($val);
                        if ($val === '') {
                            $val = null;
                        }
                    }
                    if ($val !== null && $val !== '') $hasValue = true;

                    if (in_array($dbCol, ['period', 'period_date', 'periode', 'period_dt', 'perioddate'], true)) {
                        // Always override period in strict mode to the selected period to avoid accidental skips
                        $val = $periodDate;
                    } else {
                        $colType = $targetColumnTypes[$dbCol] ?? null;
                        if ($colType && in_array($colType, $numericTypes, true)) {
                            $val = $normalizeNumeric($val, $colType);
                        } elseif ($colType && in_array($colType, $dateTypes, true)) {
                            $val = $normalizeDate($val);
                        }
                    }
                    $record[$dbCol] = $val;
                }

                if (!$hasValue) continue;

                if (isset($targetColumnMap[$periodColumnName])) {
                    $record[$periodColumnName] = $periodDate;
                }
                if (isset($targetColumnMap['upload_index'])) {
                    $record['upload_index'] = $uploadIndex;
                }
                if (isset($targetColumnMap['is_active'])) {
                    $record['is_active'] = 1;
                }
                if (isset($targetColumnMap['created_at'])) {
                    $record['created_at'] = $timestamp;
                }
                if (isset($targetColumnMap['updated_at'])) {
                    $record['updated_at'] = $timestamp;
                }

                $record = array_intersect_key($record, $targetColumnMap);

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
