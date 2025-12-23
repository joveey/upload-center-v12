<?php

namespace App\Http\Controllers;

use App\Models\MappingColumn;
use App\Models\MappingIndex;
use App\Services\UploadIndexService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class LegacyFormatController extends Controller
{
    /**
     * Get the control connection for querying mapping_indices
     */
    private function getControlConnection(): string
    {
        $controlConnection = config('database.control_connection', config('database.default'));
        $isLegacy = fn($name) => $name === 'sqlsrv_legacy' || ($name && str_starts_with($name, 'legacy_'));
        if (! $controlConnection || $isLegacy($controlConnection)) {
            $controlConnection = config('database.connections.sqlsrv') ? 'sqlsrv' : config('database.default');
        }
        return $controlConnection;
    }

    public function list(Request $request)
    {
        abort_unless($request->user(), 403);

        $defaultConnection = config('database.default');
        [$legacyConnection, $selectedDb, $legacyDatabases] = $this->resolveLegacyConnection(
            $request->string('db')->trim()->value()
        );

        $defaultDbName = config("database.connections.{$defaultConnection}.database");
        $legacyDbName = $selectedDb ?: config("database.connections.{$legacyConnection}.database");
        $search = trim((string) $request->input('q', ''));

        // Ambil seluruh tabel pada koneksi legacy (bukan hanya yang sudah dimapping)
        $legacyTables = collect(DB::connection($legacyConnection)->select(
            "SELECT TABLE_SCHEMA AS schema_name, TABLE_NAME AS table_name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'"
        ));

        // Ambil mapping yang sudah terdaftar, di-key per nama tabel
        $registeredMappings = MappingIndex::on($this->getControlConnection())->with('division')->get()->groupBy(function ($mapping) {
            $tableKey = strtolower($mapping->table_name ?? '');
            $connectionKey = strtolower((string) ($mapping->connection ?? ''));
            return $connectionKey !== '' ? "{$connectionKey}|{$tableKey}" : $tableKey;
        });

        // Gabungkan info tabel legacy + status mapping
        $collection = $legacyTables->map(function ($row) use ($registeredMappings, $defaultConnection, $legacyConnection) {
            $tableName = $row->table_name;
            $key = strtolower($tableName);
            $legacyKey = strtolower($legacyConnection) . '|' . $key;
            $mapping = $registeredMappings->get($legacyKey);
            if (! $mapping) {
                $mapping = $registeredMappings->get($key);
            }
            $mapping = $mapping ? $mapping->first() : null;
            
            // Check jika table punya companion _INDEX table (sudah di-setup untuk versioning)
            $hasIndexTable = Schema::connection($legacyConnection)->hasTable($tableName . '_INDEX');

            return (object) [
                'table_name' => $tableName,
                'schema' => $row->schema_name ?? 'dbo',
                'is_mapped' => (bool) $mapping,
                'has_index_table' => $hasIndexTable,
                'code' => $mapping->code ?? null,
                'description' => $mapping->description ?? null,
                'mapping_id' => $mapping->id ?? null,
                'exists_on_default' => Schema::connection($defaultConnection)->hasTable($tableName),
            ];
        });

        // Sembunyikan tabel yang sudah dimapping dan tabel _INDEX companion itu sendiri
        $collection = $collection->filter(function ($item) {
            // Jangan show jika sudah dimapping
            if ($item->is_mapped) {
                return false;
            }
            // Jangan show jika table ini sendiri adalah _INDEX companion (ends with _INDEX)
            if (str_ends_with($item->table_name, '_INDEX')) {
                return false;
            }
            return true;
        })->values();

        if ($search !== '') {
            $needle = strtolower($search);
            $collection = $collection->filter(function ($item) use ($needle) {
                return str_contains(strtolower($item->table_name), $needle)
                    || str_contains(strtolower($item->code ?? ''), $needle)
                    || str_contains(strtolower($item->description ?? ''), $needle);
            })->values();
        }

        $perPage = 15;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = $collection->slice(($page - 1) * $perPage, $perPage)->values();
        $mappings = new LengthAwarePaginator(
            $items,
            $collection->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('legacy.format.list', [
            'mappings' => $mappings,
            'legacyDbName' => $legacyDbName,
            'defaultDbName' => $defaultDbName,
            'search' => $search,
            'legacyDatabases' => $legacyDatabases,
            'selectedDb' => $selectedDb,
        ]);
    }

    /**
     * Preview data from a legacy table before registering a mapping.
     */
    public function preview(Request $request)
    {
        abort_unless($request->user(), 403);

        $tableName = trim((string) $request->query('table', ''));
        abort_unless($tableName !== '', 404, 'Legacy table not specified.');

        [$legacyConnection, $selectedDb, $legacyDatabases] = $this->resolveLegacyConnection(
            $request->string('db')->trim()->value()
        );
        abort_unless(
            Schema::connection($legacyConnection)->hasTable($tableName),
            404,
            'Legacy table not found.'
        );

        $columnRows = collect(DB::connection($legacyConnection)->select("
            SELECT COLUMN_NAME AS column_name, DATA_TYPE AS data_type
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ", [$tableName]));

        abort_unless($columnRows->isNotEmpty(), 404, 'No columns available for this legacy table.');

        $columnNames = $columnRows->pluck('column_name')->all();
        $displayColumns = $columnRows
            ->filter(fn ($col) => $col->column_name !== 'id')
            ->map(function ($col) {
            return [
                'name' => $col->column_name,
                'label' => $col->column_name,
            ];
        });

        $searchableColumns = $columnRows
            ->filter(function ($col) {
                $type = strtolower((string) $col->data_type);
                return in_array($type, ['char', 'varchar', 'nchar', 'nvarchar', 'text', 'ntext'], true);
            })
            ->take(5)
            ->pluck('column_name')
            ->all();

        $query = DB::connection($legacyConnection)->table($tableName)->select($columnNames);

        $search = $request->string('q')->trim();
        if ($search->isNotEmpty() && !empty($searchableColumns)) {
            $value = '%' . $search->value() . '%';
            $query->where(function ($q2) use ($value, $searchableColumns) {
                foreach ($searchableColumns as $column) {
                    $q2->orWhere($column, 'like', $value);
                }
            });
        }

        $hasIdColumn = in_array('id', $columnNames, true);
        $orderColumn = null;
        $orderDirection = 'asc';

        if ($hasIdColumn) {
            $orderColumn = 'id';
            $orderDirection = 'desc';
        } else {
            $nonOrderable = ['text', 'ntext', 'image', 'xml'];
            foreach ($columnRows as $col) {
                $type = strtolower((string) $col->data_type);
                if (!in_array($type, $nonOrderable, true)) {
                    $orderColumn = $col->column_name;
                    break;
                }
            }
        }

        if ($orderColumn !== null) {
            $query->orderBy($orderColumn, $orderDirection);
        }

        $data = $query->paginate(50)->withQueryString();
        $legacyDbName = $selectedDb ?: config("database.connections.{$legacyConnection}.database");

        return view('legacy.format.preview', [
            'tableName' => $tableName,
            'legacyDbName' => $legacyDbName,
            'columns' => $displayColumns,
            'data' => $data,
            'showIdColumn' => $hasIdColumn,
            'search' => $search->value(),
            'legacyDatabases' => $legacyDatabases,
            'selectedDb' => $selectedDb,
        ]);
    }

    /**
     * Quick-map tabel legacy agar langsung terdaftar di mapping_indices.
     */
    public function quickMap(Request $request): RedirectResponse
    {
        abort_unless($request->user(), 403);

        $validated = $request->validate([
            'table_name' => 'required|string',
        ]);

        $tableName = $validated['table_name'];
        [$legacyConnection, $selectedDb] = $this->resolveLegacyConnection(
            $request->string('db')->trim()->value()
        );

        if (! Schema::connection($legacyConnection)->hasTable($tableName)) {
            return back()->with('error', "Tabel '{$tableName}' tidak ditemukan di koneksi legacy.");
        }

        // Jika sudah ada, langsung arahkan ke halaman legacy format.
        $controlConnection = $this->getControlConnection();
        if ($existing = MappingIndex::on($controlConnection)->where('table_name', $tableName)->first()) {
            if (Schema::hasColumn('mapping_indices', 'connection') && empty($existing->connection)) {
                $existing->connection = $legacyConnection;
                $existing->save();
            }
            return redirect()->route('legacy.format.index', [
                'mapping' => $existing->id,
                'db' => $selectedDb,
            ])
                ->with('info', "Tabel '{$tableName}' sudah dimapping sebelumnya.");
        }

        $divisionId = $request->user()->division_id ?? DB::table('divisions')->value('id');
        if (! $divisionId) {
            return back()->with('error', 'Division ID tidak ditemukan untuk membuat mapping.');
        }

        // Pastikan code unik (slug dari nama tabel, tambahkan suffix jika perlu)
        $baseCode = Str::slug($tableName, '_');
        if ($baseCode === '') {
            $baseCode = 'legacy_table';
        }

        $code = $baseCode;
        $suffix = 2;
        while (MappingIndex::on($controlConnection)->where('code', $code)->exists()) {
            $code = "{$baseCode}_{$suffix}";
            $suffix++;
        }


        $payload = [
            'division_id' => $divisionId,
            'code' => $code,
            'description' => $tableName,
            'table_name' => $tableName,
            'header_row' => 1,
        ];
        if (Schema::hasColumn('mapping_indices', 'connection')) {
            $payload['connection'] = $legacyConnection;
        }

        $mapping = MappingIndex::create($payload);

        // Auto-map kolom berdasarkan skema tabel legacy
        $columns = DB::connection($legacyConnection)->select("
            SELECT COLUMN_NAME AS column_name, DATA_TYPE AS data_type
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = ? AND COLUMN_NAME NOT IN ('id','created_at','updated_at','deleted_at')
            ORDER BY ORDINAL_POSITION
        ", [$tableName]);

        $excelIndex = 0;
        foreach ($columns as $col) {
            $excelIndex++;
            $excelColumn = $this->numberToColumnLetter($excelIndex);
            $colName = $col->column_name;

            // Map beberapa tipe umum ke label internal
            $type = strtolower($col->data_type ?? 'string');
            $mappedType = match (true) {
                str_contains($type, 'int') => 'integer',
                str_contains($type, 'decimal') || str_contains($type, 'numeric') || str_contains($type, 'money') => 'decimal',
                str_contains($type, 'date') || str_contains($type, 'time') => 'date',
                default => 'string',
            };

            MappingColumn::create([
                'mapping_index_id' => $mapping->id,
                'excel_column_index' => $excelColumn,
                'table_column_name' => $colName,
                'data_type' => $mappedType,
                'is_required' => false,
                'is_unique_key' => false,
            ]);
        }

        return redirect()->route('legacy.format.index', [
            'mapping' => $mapping->id,
            'db' => $selectedDb,
        ])
            ->with('success', "Tabel '{$tableName}' berhasil dimapping otomatis.");
    }

    public function index(Request $request, $mapping)
    {
        $user = $request->user();

        abort_unless($user, 403);

        $mappingIndex = MappingIndex::with('columns')
            ->where('id', $mapping)
            ->orWhere('code', $mapping)
            ->firstOrFail();

        [$legacyConnection, $selectedDb, $legacyDatabases] = $this->resolveLegacyConnection(
            $request->string('db')->trim()->value()
        );
        $dbOverride = $request->filled('db');

        $connection = $mappingIndex->target_connection
            ?? $mappingIndex->connection
            ?? config('database.default');

        $tableName = $mappingIndex->table_name;

        $this->ensureLegacyConnectionConfigured($connection);
        if ($dbOverride) {
            $connection = $legacyConnection;
        } else {
            // Jika tabel tidak ada di koneksi saat ini, tetapi ada di legacy, pakai koneksi legacy.
            if (! Schema::connection($connection)->hasTable($tableName) && Schema::connection('sqlsrv_legacy')->hasTable($tableName)) {
                $connection = 'sqlsrv_legacy';
            }
        }

        abort_unless(Schema::connection($connection)->hasTable($tableName), 404, 'Legacy table not found.');

        $actualColumns = Schema::connection($connection)->getColumnListing($tableName);

        $mappedColumns = $mappingIndex->columns
            ->sortBy(fn ($col) => $this->columnLetterToIndex($col->excel_column_index))
            ->filter(fn ($col) => in_array($col->table_column_name, $actualColumns, true))
            ->values();

        abort_unless($mappedColumns->isNotEmpty(), 404, 'No mapped columns available for this legacy mapping.');

        $displayColumns = $mappedColumns->map(function ($col) {
            return [
                'name' => $col->table_column_name,
                'label' => $col->table_column_name,
            ];
        });

        $searchableColumns = $mappedColumns
            ->filter(fn ($col) => in_array($col->data_type, ['string', 'text', null], true))
            ->take(5)
            ->pluck('table_column_name')
            ->all();

        $periodFilter = $request->query('period_date');
        $activeRun = null;
        $legacyActiveIndex = null;
        /** @var UploadIndexService $uploadIndexService */
        $uploadIndexService = app(UploadIndexService::class);
        $activeRun = $uploadIndexService->getActiveRun($mappingIndex->id, $periodFilter);
        if (! $activeRun) {
            $legacyActiveIndex = $this->getActiveUploadIndexFromLegacy($tableName, $connection, $periodFilter);
        }
        if ($activeRun && $activeRun->period_date) {
            $candidate = $uploadIndexService->buildVersionTableName($tableName, $activeRun->period_date, (int) $activeRun->upload_index);
            if (Schema::connection($connection)->hasTable($candidate)) {
                $tableName = $candidate;
                $actualColumns = Schema::connection($connection)->getColumnListing($tableName);
            }
        }

        $query = DB::connection($connection)->table($tableName);
        $uploadIndexFilter = $activeRun?->upload_index ?? $legacyActiveIndex;
        if (in_array('upload_index', $actualColumns, true) && $uploadIndexFilter !== null) {
            if ($tableName === $mappingIndex->table_name) {
                $query->where(function ($q2) use ($uploadIndexFilter) {
                    $q2->where('upload_index', $uploadIndexFilter)
                        ->orWhereNull('upload_index');
                });
            } else {
                $query->where('upload_index', $uploadIndexFilter);
            }
        }

        $hasIdColumn = in_array('id', $actualColumns, true);
        $orderColumn = $hasIdColumn
            ? 'id'
            : $mappedColumns->first()->table_column_name;

        $search = $request->string('q')->trim();
        if ($search->isNotEmpty() && !empty($searchableColumns)) {
            $value = '%' . $search . '%';
            $query->where(function ($q2) use ($value, $searchableColumns) {
                foreach ($searchableColumns as $column) {
                    $q2->orWhere($column, 'like', $value);
                }
            });
        }

        $divisionFilterApplied = false;
        if (in_array('division_code', $actualColumns, true)) {
            $query->where(function ($q2) {
                $q2->whereNull('division_code')
                    ->orWhere('division_code', '');
            });
            $divisionFilterApplied = true;
        }

        $data = $query->orderByDesc($orderColumn)->paginate(50)->withQueryString();

        return view('legacy.format.index', [
            'mapping' => $mappingIndex,
            'columns' => $displayColumns,
            'data' => $data,
            'showIdColumn' => $hasIdColumn,
            'divisionFilterApplied' => $divisionFilterApplied,
            'search' => $search->value(),
            'legacyDatabases' => $legacyDatabases,
            'selectedDb' => $selectedDb,
        ]);
    }

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
        ];
    }

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

    private function legacyDatabaseOptions(): array
    {
        $options = config('database.legacy_databases', []);
        $options = is_array($options) ? $options : [];
        $options = array_filter(array_map('trim', $options));
        $defaultDb = (string) config('database.connections.sqlsrv_legacy.database');

        if ($defaultDb !== '' && ! in_array($defaultDb, $options, true)) {
            array_unshift($options, $defaultDb);
        }

        return array_values(array_unique($options));
    }

    private function resolveLegacyConnection(?string $selectedDb): array
    {
        $defaultDb = (string) config('database.connections.sqlsrv_legacy.database');
        $available = $this->legacyDatabaseOptions();

        $selectedDb = trim((string) $selectedDb);
        if ($selectedDb === '') {
            $selectedDb = $defaultDb;
        }

        if ($selectedDb !== '' && ! in_array($selectedDb, $available, true)) {
            $selectedDb = $defaultDb;
        }

        $connectionName = 'sqlsrv_legacy';
        if ($selectedDb !== '' && $selectedDb !== $defaultDb) {
            $connectionName = 'legacy_' . Str::slug($selectedDb, '_');
            if (! config("database.connections.{$connectionName}")) {
                $baseConfig = config('database.connections.sqlsrv_legacy');
                $baseConfig['database'] = $selectedDb;
                config(["database.connections.{$connectionName}" => $baseConfig]);
            }
        }

        return [$connectionName, $selectedDb, $available];
    }

    private function columnLetterToIndex(?string $letter): int
    {
        if (empty($letter)) {
            return PHP_INT_MAX;
        }
        $letter = strtoupper($letter);
        $length = strlen($letter);
        $index = 0;

        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($letter[$i]) - ord('A') + 1);
        }

        return $index;
    }

    private function numberToColumnLetter(int $number): string
    {
        $letter = '';
        while ($number > 0) {
            $mod = ($number - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $number = intdiv($number - 1, 26);
        }
        return $letter;
    }
}
