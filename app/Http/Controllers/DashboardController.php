<?php

namespace App\Http\Controllers;

use App\Models\MappingColumn;
use App\Models\MappingIndex;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the user's dashboard.
     */
    public function index(): View
    {
        $user = Auth::user();
        $mappings = MappingIndex::with('division')
            ->orderBy('description')
            ->get();
        $uploadStats = null;
        $divisionUploadCounts = $this->getDivisionUploadCounts();
        $recentActivities = \App\Models\UploadLog::with(['mappingIndex', 'user', 'division'])
            ->latest()
            ->take(10)
            ->get();
        $stats = $this->getDashboardStats();

        return view('dashboard', [
            'mappings' => $mappings,
            'uploadStats' => $uploadStats,
            'divisionUploadCounts' => $divisionUploadCounts,
            'recentActivities' => $recentActivities,
            'stats' => $stats,
        ]);
    }

    /**
     * Get total upload counts per division (all divisions, including superuser)
     */
    private function getDivisionUploadCounts(): array
    {
        $divisions = \App\Models\Division::pluck('name', 'id');
        $legacyLabel = 'Legacy';
        $superDivisionNames = \App\Models\Division::where('is_super_user', true)->pluck('name')->all();

        // Hitung jumlah format per divisi (bukan upload rows)
        $formatCounts = [];
        $mappings = \App\Models\MappingIndex::select('id', 'division_id', 'table_name')->get();
        foreach ($mappings as $mapping) {
            // Jika tabel hanya ada di legacy, anggap milik Legacy
            $isLegacy = $mapping->table_name
                && !Schema::hasTable($mapping->table_name)
                && Schema::connection('sqlsrv_legacy')->hasTable($mapping->table_name);

            $name = $isLegacy
                ? $legacyLabel
                : ($divisions[$mapping->division_id] ?? $legacyLabel);

            $formatCounts[$name] = ($formatCounts[$name] ?? 0) + 1;
        }

        // Pastikan semua divisi muncul, default 0
        $filteredDivisions = $divisions->filter(fn ($name) => !in_array($name, $superDivisionNames, true));
        $allNames = array_unique(array_merge(array_values($filteredDivisions->toArray()), [$legacyLabel], array_keys($formatCounts)));

        return collect($allNames)
            ->map(fn ($name) => ['name' => $name, 'count' => $formatCounts[$name] ?? 0])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * Get upload statistics for the last 4 weeks
     */
    private function getUploadStatistics(): array
    {
        $weeks = [];
        $labels = [];
        
        // Generate last 4 weeks
        for ($i = 3; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $weekEnd = now()->subWeeks($i)->endOfWeek();
            $weeks[] = ['start' => $weekStart, 'end' => $weekEnd];
            $labels[] = $weekStart->format('d M') . ' - ' . $weekEnd->format('d M');
        }

        // Get all divisions (exclude superuser)
        $divisions = \App\Models\Division::where('is_super_user', false)->get();
        
        $datasets = [];
        foreach ($divisions as $division) {
            $data = [];
            $hasData = false;
            
            foreach ($weeks as $week) {
                // Count uploads for this division in this week
                $count = \App\Models\UploadLog::where('division_id', $division->id)
                    ->whereBetween('created_at', [$week['start'], $week['end']])
                    ->count();
                $data[] = $count;
                
                if ($count > 0) {
                    $hasData = true;
                }
            }
            
            // Only include divisions that have upload data
            if ($hasData) {
                $datasets[] = [
                    'label' => $division->name,
                    'data' => $data,
                ];
            }
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * Additional dashboard stats and alerts.
     */
    private function getDashboardStats(): array
    {
        $totalFormats = MappingIndex::count();
        $legacyFormats = MappingIndex::all()->filter(function ($m) {
            $table = $m->table_name;
            return $table && !Schema::hasTable($table) && Schema::connection('sqlsrv_legacy')->hasTable($table);
        })->count();

        $uploads30 = \App\Models\UploadLog::where('action', 'upload')
            ->where('status', 'success')
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('rows_imported');

        $failed7 = \App\Models\UploadLog::where('action', 'upload')
            ->where('status', '!=', 'success')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $topFormats = \App\Models\UploadLog::selectRaw('mapping_index_id, SUM(rows_imported) as total_rows, COUNT(*) as uploads')
            ->where('action', 'upload')
            ->where('status', 'success')
            ->groupBy('mapping_index_id')
            ->orderByDesc('total_rows')
            ->with('mappingIndex')
            ->take(5)
            ->get();

        $failedUploads = \App\Models\UploadLog::with(['mappingIndex', 'user', 'division'])
            ->where('action', 'upload')
            ->where('status', '!=', 'success')
            ->latest()
            ->take(5)
            ->get();

        // Simple health check
        $health = [
            'default_db' => Schema::hasTable('mapping_indices'),
            'legacy_db' => Schema::connection('sqlsrv_legacy')->hasTable('mapping_indices'),
            'logs' => Schema::hasTable('upload_logs'),
        ];

        return [
            'total_formats' => $totalFormats,
            'legacy_formats' => $legacyFormats,
            'uploads_30d' => $uploads30,
            'failed_7d' => $failed7,
            'top_formats' => $topFormats,
            'failed_uploads' => $failedUploads,
            'health' => $health,
        ];
    }

    /**
     * Store a new format mapping and create the table.
     */
    public function store(Request $request): RedirectResponse
    {
        Log::info('Memulai proses pendaftaran format & pembuatan tabel baru.');

        // Normalize mapping inputs to avoid false duplicate detection
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
            'mappings.*.database_column' => ['required', 'string', 'distinct', 'regex:/^[a-z0-9_]+$/', Rule::notIn(['id'])],
            'mappings.*.is_unique_key' => 'nullable|boolean',
        ], [
            'name.unique' => 'Nama format ini sudah digunakan.',
            'table_name.regex' => 'Nama tabel hanya boleh berisi huruf kecil, angka, dan underscore (_).',
            'table_name.unique' => 'Nama tabel ini sudah digunakan oleh format lain.',
            'mappings.*.database_column.regex' => 'Nama kolom hanya boleh berisi huruf kecil, angka, dan underscore (_).',
            'mappings.*.database_column.not_in' => 'Nama kolom tidak boleh "id". Kolom "id" akan dibuat secara otomatis.',
        ]);

        Log::info('Validasi berhasil.', $validated);
        $tableName = $validated['table_name'];

        if (Schema::hasTable($tableName)) {
            return back()->with('error', "Tabel dengan nama '{$tableName}' sudah ada di database. Silakan gunakan nama lain.")->withInput();
        }

        DB::beginTransaction();
        try {
            // Buat tabel baru
            Schema::create($tableName, function (Blueprint $table) use ($validated) {
                $table->id();
                foreach ($validated['mappings'] as $mapping) {
                    $table->text($mapping['database_column'])->nullable();
                }
                $table->timestamps();
            });
            Log::info("Tabel '{$tableName}' berhasil dibuat.");

            // Simpan format ke mapping_indices
            $mappingIndex = MappingIndex::create([
                'code' => strtolower(str_replace(' ', '_', $validated['name'])),
                'description' => $validated['name'],
                'table_name' => $tableName,
                'header_row' => $validated['header_row'],
                'division_id' => Auth::user()->division_id,
            ]);
            Log::info("Format berhasil disimpan di mapping_indices dengan ID: {$mappingIndex->id}");

            // Simpan mapping kolom dengan status is_unique_key
            foreach ($validated['mappings'] as $mapping) {
                MappingColumn::create([
                    'mapping_index_id' => $mappingIndex->id,
                    'excel_column_index' => strtoupper($mapping['excel_column']),
                    'table_column_name' => $mapping['database_column'],
                    'data_type' => 'string',
                    'is_required' => false,
                    'is_unique_key' => isset($mapping['is_unique_key']) && $mapping['is_unique_key'] ? true : false,
                ]);
            }
            Log::info("Pemetaan kolom berhasil disimpan dengan konfigurasi kunci unik.");

            DB::commit();
            return redirect()->route('dashboard')->with('success', "Format '{$validated['name']}' berhasil disimpan dan tabel '{$tableName}' telah dibuat!");

        } catch (\Exception $e) {
            DB::rollBack();
            Schema::dropIfExists($tableName);
            Log::error('Gagal membuat tabel/format: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }

        // Catat log pembuatan format (fallback untuk alur ini)
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
        } catch (\Throwable $logEx) {
            Log::warning('Gagal mencatat log pembuatan format (dashboard store): ' . $logEx->getMessage());
        }
    }
}
