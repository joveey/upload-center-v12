<?php

namespace App\Http\Controllers;

use App\Models\MappingColumn;
use App\Models\MappingIndex;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class LegacyFormatController extends Controller
{
    public function list(Request $request)
    {
        abort_unless($request->user(), 403);

        $defaultConnection = config('database.default');
        $legacyConnection = 'sqlsrv_legacy';

        $defaultDbName = config("database.connections.{$defaultConnection}.database");
        $legacyDbName = config("database.connections.{$legacyConnection}.database");
        $search = trim((string) $request->input('q', ''));

        // Ambil seluruh tabel pada koneksi legacy (bukan hanya yang sudah dimapping)
        $legacyTables = collect(DB::connection($legacyConnection)->select(
            "SELECT TABLE_SCHEMA AS schema_name, TABLE_NAME AS table_name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'"
        ));

        // Ambil mapping yang sudah terdaftar, di-key per nama tabel
        $registeredMappings = MappingIndex::with('division')->get()->keyBy(function ($mapping) {
            return strtolower($mapping->table_name ?? '');
        });

        // Gabungkan info tabel legacy + status mapping
        $collection = $legacyTables->map(function ($row) use ($registeredMappings, $defaultConnection) {
            $tableName = $row->table_name;
            $key = strtolower($tableName);
            $mapping = $registeredMappings->get($key);

            return (object) [
                'table_name' => $tableName,
                'schema' => $row->schema_name ?? 'dbo',
                'is_mapped' => (bool) $mapping,
                'code' => $mapping->code ?? null,
                'description' => $mapping->description ?? null,
                'mapping_id' => $mapping->id ?? null,
                'exists_on_default' => Schema::connection($defaultConnection)->hasTable($tableName),
            ];
        });

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
        $legacyConnection = 'sqlsrv_legacy';

        if (! Schema::connection($legacyConnection)->hasTable($tableName)) {
            return back()->with('error', "Tabel '{$tableName}' tidak ditemukan di koneksi legacy.");
        }

        // Jika sudah ada, langsung arahkan ke halaman legacy format.
        if ($existing = MappingIndex::where('table_name', $tableName)->first()) {
            return redirect()->route('legacy.format.index', $existing->id)
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
        while (MappingIndex::where('code', $code)->exists()) {
            $code = "{$baseCode}_{$suffix}";
            $suffix++;
        }

        $mapping = MappingIndex::create([
            'division_id' => $divisionId,
            'code' => $code,
            'description' => $tableName,
            'table_name' => $tableName,
            'header_row' => 1,
        ]);

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

        return redirect()->route('legacy.format.index', $mapping->id)
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

        $connection = $mappingIndex->target_connection
            ?? $mappingIndex->connection
            ?? config('database.default');

        $tableName = $mappingIndex->table_name;

        // Jika tabel tidak ada di koneksi saat ini, tetapi ada di legacy, pakai koneksi legacy.
        if (! Schema::connection($connection)->hasTable($tableName) && Schema::connection('sqlsrv_legacy')->hasTable($tableName)) {
            $connection = 'sqlsrv_legacy';
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

        $query = DB::connection($connection)->table($tableName);

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
        ]);
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
