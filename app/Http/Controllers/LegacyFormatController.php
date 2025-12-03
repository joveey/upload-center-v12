<?php

namespace App\Http\Controllers;

use App\Models\MappingIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;

class LegacyFormatController extends Controller
{
    public function list(Request $request)
    {
        abort_unless($request->user(), 403);

        // Ambil semua mapping lalu filter hanya berdasarkan ketersediaan tabel di koneksi legacy
        $defaultConnection = config('database.default');
        $legacyConnection = 'sqlsrv_legacy';

        $defaultDbName = config("database.connections.{$defaultConnection}.database");
        $legacyDbName = config("database.connections.{$legacyConnection}.database");
        $sameDatabase = $defaultDbName === $legacyDbName;

        $search = trim((string) $request->input('q', ''));
        $query = MappingIndex::with('division')->orderBy('description');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where('description', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('table_name', 'like', $like);
            });
        }

        $collection = $query->get()->filter(function ($mapping) use ($legacyConnection, $defaultConnection, $sameDatabase) {
            if (! $mapping->table_name) {
                return false;
            }

            $existsOnLegacy = Schema::connection($legacyConnection)->hasTable($mapping->table_name);
            $existsOnDefault = Schema::connection($defaultConnection)->hasTable($mapping->table_name);

            if (! $existsOnLegacy) {
                return false;
            }

            // Jika DB berbeda, hanya tampilkan tabel yang tidak ada di default (hindari yang milik DB utama).
            if (! $sameDatabase && $existsOnDefault) {
                return false;
            }

            return true;
        })->values();

        $perPage = 10;
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
            'sameDatabase' => $sameDatabase,
            'search' => $search,
        ]);
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
}
