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

        return view('dashboard', [
            'mappings' => $mappings,
            'uploadStats' => $uploadStats,
            'divisionUploadCounts' => $divisionUploadCounts,
            'recentActivities' => $recentActivities,
        ]);
    }

    /**
     * Get total upload counts per division (all divisions, including super-admin)
     */
    private function getDivisionUploadCounts(): array
    {
        $divisions = \App\Models\Division::pluck('name', 'id');

        $rows = DB::table('upload_logs as ul')
            ->leftJoin('mapping_indices as mi', 'mi.id', '=', 'ul.mapping_index_id')
            ->leftJoin('users as u', 'u.id', '=', 'ul.user_id')
            ->selectRaw('COALESCE(u.division_id, ul.division_id, mi.division_id) as division_id, COUNT(*) as total')
            ->groupBy(DB::raw('COALESCE(u.division_id, ul.division_id, mi.division_id)'))
            ->get();

        $counts = [];

        // Aggregate from upload logs
        foreach ($rows as $row) {
            $divisionId = $row->division_id;
            $name = $divisions[$divisionId] ?? 'Tanpa Divisi';
            $counts[$name] = ($counts[$name] ?? 0) + (int) $row->total;
        }

        // Fallback: aggregate from actual data tables per mapping division
        $mappings = \App\Models\MappingIndex::select('id', 'division_id', 'table_name')->get();
        foreach ($mappings as $mapping) {
            if ($mapping->table_name && Schema::hasTable($mapping->table_name)) {
                $rowCount = DB::table($mapping->table_name)->count();
                if ($rowCount > 0) {
                    $name = $divisions[$mapping->division_id] ?? 'Tanpa Divisi';
                    $counts[$name] = ($counts[$name] ?? 0) + $rowCount;
                }
            }
        }

        return collect($counts)
            ->map(fn ($count, $name) => ['name' => $name, 'count' => $count])
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
    }
}
