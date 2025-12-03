<?php

namespace App\Http\Controllers;

use App\Models\MappingColumn;
use App\Models\MappingIndex;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
                    ->orWhere('table_name', 'like', $like);
            });
        }

        $perPage = 10;

        $mappings = $query->orderBy('description')->paginate($perPage)->withQueryString();
        
        // Get statistics for each mapping
        $mappings->getCollection()->each(function ($mapping) use ($user) {
            $tableName = $mapping->table_name;
            $connection = $mapping->target_connection
                ?? $mapping->connection
                ?? config('database.default');

            // Jika tabel tidak ada di koneksi utama mapping tapi ada di legacy, gunakan legacy
            if (!Schema::connection($connection)->hasTable($tableName) && Schema::connection('sqlsrv_legacy')->hasTable($tableName)) {
                $connection = 'sqlsrv_legacy';
            }

            $mapping->is_legacy_source = ($connection === 'sqlsrv_legacy');
            
            if (Schema::connection($connection)->hasTable($tableName)) {
                $mapping->row_count = DB::connection($connection)->table($tableName)->count();
            } else {
                $mapping->row_count = 0;
            }
        });
        
        $totalFormats = $mappings->total();
        $totalColumns = \App\Models\MappingColumn::whereIn(
            'mapping_index_id',
            (clone $query)->select('id')
        )->count();
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
     * View data from a specific format/mapping table
     */
    public function viewData($mappingId): View|RedirectResponse
    {
        $user = Auth::user();
        $mapping = MappingIndex::with('columns')->findOrFail($mappingId);

        // Pilih koneksi DB sesuai mapping (fallback ke default/legacy)
        $connection = $mapping->target_connection
            ?? $mapping->connection
            ?? config('database.default');
        $tableName = $mapping->table_name;

        // Jika tabel tidak ada di koneksi terpilih tapi ada di legacy, pakai legacy
        if (!Schema::connection($connection)->hasTable($tableName) && Schema::connection('sqlsrv_legacy')->hasTable($tableName)) {
            $connection = 'sqlsrv_legacy';
        }

        if (!Schema::connection($connection)->hasTable($tableName)) {
            return back()->with('error', "Tabel '{$tableName}' tidak ditemukan di koneksi {$connection}.");
        }
        
        // Get column mapping sorted by Excel column order
        $columnMapping = $mapping->columns
            ->sortBy(fn($col) => $this->columnLetterToIndex($col->excel_column_index))
            ->pluck('table_column_name', 'excel_column_index')
            ->toArray();

        if (empty($columnMapping)) {
            return back()->with('error', 'Tidak ada kolom yang di-mapping untuk format ini.');
        }

        // Get actual table columns
        $actualTableColumns = Schema::connection($connection)->getColumnListing($tableName);
        $validColumns = array_intersect(array_values($columnMapping), $actualTableColumns);

        if (empty($validColumns)) {
            return back()->with('error', 'Konfigurasi mapping tidak sesuai dengan skema tabel.');
        }

        // Pastikan hanya memilih kolom yang benar-benar ada
        $selectColumns = array_values(array_unique(array_filter(array_merge(['id'], $validColumns, ['created_at', 'updated_at']), function ($col) use ($actualTableColumns) {
            return in_array($col, $actualTableColumns, true);
        })));

        // Build query
        $query = DB::connection($connection)->table($tableName)->select($selectColumns);
        
        // Filter by division if not super-admin
        if (!$this->userHasRole($user, 'super-admin')) {
            if (in_array('division_id', $actualTableColumns)) {
                $query->where('division_id', $user->division_id);
            }
        }
        
        // Paginate results - order by ID ascending (smallest first)
        $data = $query->orderBy('id', 'asc')->paginate(50);

        return view('view_data', [
            'mapping' => $mapping,
            'columns' => $validColumns,
            'data' => $data,
            'columnMapping' => $columnMapping,
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
                'data_file' => ['required', File::types(['xlsx', 'xls'])->max(40960)], // 40 MB
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
                'data_file' => ['required', File::types(['xlsx', 'xls'])->max(40960)], // 40 MB
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
        $html .= '<p class="text-sm text-gray-600 mt-1">Hapus data dengan period hari ini, lalu insert semua data baru dari file</p>';
        $html .= '<p class="text-xs text-red-600 mt-1 font-medium">⚠️ Data period hari ini akan dihapus dan diganti dengan data baru!</p>';
        $html .= '</div>';
        $html .= '</label>';
        
        $html .= '</div>';
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

        if (!$this->userHasRole($user, 'super-admin') && $mapping->division_id !== $user->division_id) {
            return redirect()
                ->route('formats.index')
                ->with('error', 'Anda tidak memiliki akses untuk menghapus isi format ini.');
        }

        $tableName = $mapping->table_name;
        if (!$tableName || !Schema::hasTable($tableName)) {
            return redirect()->route('formats.index')->with('error', "Tabel '{$tableName}' tidak ditemukan.");
        }

        try {
            $rowCount = DB::table($tableName)->count();

            $driver = DB::connection()->getDriverName();

            if ($driver === 'sqlsrv') {
                DB::statement("TRUNCATE TABLE [{$tableName}]");
            } elseif ($driver === 'pgsql') {
                DB::statement("TRUNCATE TABLE \"{$tableName}\" RESTART IDENTITY CASCADE");
            } else {
                DB::statement("TRUNCATE TABLE `{$tableName}`");
            }

            Log::warning("Tabel {$tableName} dikosongkan & ID di-reset ({$rowCount} baris dihapus) oleh user {$user->id}");

            return redirect()
                ->route('formats.index')
                ->with('success', "Isi tabel '{$tableName}' berhasil dihapus dan ID di-reset (total {$rowCount} baris).");
        } catch (\Exception $e) {
            Log::error("Gagal mengosongkan tabel {$tableName}: " . $e->getMessage());
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

        // Only allow same division unless super-admin
        if (!$this->userHasRole($user, 'super-admin') && $mapping->division_id !== $user->division_id) {
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

            return redirect()
                ->route('formats.index')
                ->with('success', "Format '{$mapping->description}' dan tabel '{$tableName}' berhasil dihapus.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal menghapus format: ' . $e->getMessage());

            return redirect()
                ->route('formats.index')
                ->with('error', 'Gagal menghapus format: ' . $e->getMessage());
        }
    }

    /**
     * Convert Excel date serial number to readable date format
     * Excel stores dates as numbers (days since 1900-01-01)
     * Also handles string dates like DD/MM/YYYY or DD-MM-YYYY
     */
    private function convertExcelDate($value)
    {
        // If value is null or empty, return as is
        if ($value === null || $value === '') {
            return $value;
        }

        // If value is already in YYYY-MM-DD format, return as is
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        // Try to parse string dates in DD/MM/YYYY or DD-MM-YYYY format
        if (is_string($value)) {
            // Match DD/MM/YYYY or D/M/YYYY (e.g., 26/12/2025, 6/5/2020)
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches)) {
                try {
                    $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                    $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                    $year = $matches[3];
                    
                    // Validate date
                    if (checkdate($month, $day, $year)) {
                        return "{$year}-{$month}-{$day}";
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to parse date string: {$value}");
                }
            }
            
            // Match DD-MM-YYYY or D-M-YYYY (e.g., 26-12-2025, 6-5-2020)
            if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $value, $matches)) {
                try {
                    $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                    $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                    $year = $matches[3];
                    
                    // Validate date
                    if (checkdate($month, $day, $year)) {
                        return "{$year}-{$month}-{$day}";
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to parse date string: {$value}");
                }
            }
        }

        // Check if value is a numeric Excel date serial number
        // Excel dates start from 1 (1900-01-01) but we use 18264 (1950) as minimum
        // to avoid converting small IDs (1-18263) to dates
        // Range: 18264 (1950-01-01) to 60000 (2064-03-01)
        // This covers birth dates from 1950 onwards and business dates
        if (is_numeric($value) && $value >= 18264 && $value <= 60000) {
            try {
                // Excel's epoch starts at 1900-01-01, but has a leap year bug
                // Days are counted from December 30, 1899
                $unixTimestamp = ($value - 25569) * 86400;
                
                // Convert to Carbon/DateTime
                $date = \Carbon\Carbon::createFromTimestamp($unixTimestamp);
                
                // Return in YYYY-MM-DD format
                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                Log::warning("Failed to convert Excel date: {$value}, Error: " . $e->getMessage());
                return $value;
            }
        }

        // Return value as is if not a date
        return $value;
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
        
        try {
            // Allow PHP to notice when client disconnects so we can stop early
            @ignore_user_abort(false);
            @set_time_limit(0);
            @ini_set('max_execution_time', '0');
            $validated = $request->validate([
                'data_file' => ['required', File::types(['xlsx', 'xls'])->max(40960)], // 40 MB
                'mapping_id' => ['required', 'integer', Rule::exists('mapping_indices', 'id')],
                'selected_columns' => ['nullable', 'string'],
                'upload_mode' => ['required', 'string', Rule::in(['upsert', 'strict'])],
                'sheet_name' => ['nullable', 'string'],
            ]);
            
            // Automatically use today's date as period_date
            $periodDate = now()->toDateString();
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

            if (! Schema::connection($connection)->hasTable($mainTableName) && Schema::connection('sqlsrv_legacy')->hasTable($mainTableName)) {
                $connection = 'sqlsrv_legacy';
            }

            // Pakai koneksi terpilih untuk semua operasi berikutnya
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

            $isLegacyConnection = $connection === 'sqlsrv_legacy' || $mapping->connection === 'sqlsrv_legacy' || $mapping->target_connection === 'sqlsrv_legacy';

            // If upsert mode but no unique keys, choose fallback:
            // - For legacy: append (no delete, just insert)
            // - For others: strict (replace)
            if ($uploadMode === 'upsert' && empty($uniqueKeyColumns)) {
                if ($isLegacyConnection) {
                    Log::warning('Upsert mode selected but no unique keys defined. Switching to append mode for legacy.');
                    $uploadMode = 'append';
                } else {
                    Log::warning('Upsert mode selected but no unique keys defined. Switching to strict mode.');
                    $uploadMode = 'strict';
                }
            }

            // Create mapping array: excel_column_index => table_column_name
            $columnMapping = $mappingRules->pluck('table_column_name', 'excel_column_index')->toArray();
            Log::info('Column mapping:', $columnMapping);

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
            $excelPath = $request->file('data_file')->getPathname();
            $aborted = false;
            // Use a pipe delimiter for SQL Server bulk insert to avoid commas inside data shifting columns
            $bulkDelimiter = '|';
            $orderedColumns = array_values($columnMapping);
            $stagingColumns = array_merge(
                $orderedColumns,
                $hasPeriodDate ? ['period_date'] : [],
                ['created_at', 'updated_at']
            );
            $columnIndexes = [];
            foreach ($columnMapping as $excelColumn => $dbColumn) {
                // PhpSpreadsheet column index is 1-based
                $columnIndexes[$dbColumn] = $this->columnLetterToIndex($excelColumn) + 1;
            }
            $dataStartRow = max(1, $headerRow + 1); // 1-based row where data begins
            $sheetRowCount = $this->getWorksheetRowCount($request->file('data_file'), $sheetName);

            // ========================================
            // STAGING TABLE PATTERN IMPLEMENTATION
            // ========================================
            
            $driver = DB::connection()->getDriverName();

            // Step 1: Create staging table with unique random name
            $stagingTableName = 'staging_' . $mainTableName . '_' . Str::random(8);
            Log::info("Step 1: Creating staging table: {$stagingTableName}");
            
            // Create staging table with identical structure INCLUDING unique constraint
            Schema::connection($connection)->create($stagingTableName, function (Blueprint $table) use ($columnMapping, $uniqueKeyColumns, $driver, $uploadMode, $hasPeriodDate) {
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
                // Keep timestamps as strings to avoid bulk insert conversion issues
                $table->string('created_at', 30)->nullable();
                $table->string('updated_at', 30)->nullable();
                
                // Add unique constraint if there are unique key columns for UPSERT mode
                if (!empty($uniqueKeyColumns) && !($driver === 'sqlsrv' && $uploadMode === 'upsert')) {
                    $table->unique($uniqueKeyColumns, 'staging_unique_key_' . Str::random(6));
                    Log::info("Staging table unique constraint created on: " . implode(', ', $uniqueKeyColumns));
                }
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
                $tmpDir = storage_path('app/tmp');
                if (!is_dir($tmpDir)) {
                    mkdir($tmpDir, 0777, true);
                }
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
            $streamStart = microtime(true);

            $reader = IOFactory::createReaderForFile($excelPath);
            $reader->setReadDataOnly(true);
            if (method_exists($reader, 'setLoadSheetsOnly')) {
                $reader->setLoadSheetsOnly([$sheetName]);
            }

            $chunkRowCount = 5000; // rows per read chunk to control memory
            $startRow = $dataStartRow;

            while ($startRow <= $sheetRowCount) {
                if ($cancelKey && Cache::get($cancelKey)) {
                    Log::warning("Upload canceled via flag before reading chunk starting at row {$startRow}");
                    $aborted = true;
                    break;
                }

                if (function_exists('connection_aborted') && connection_aborted()) {
                    Log::warning("Upload aborted by client before reading chunk starting at row {$startRow}.");
                    $aborted = true;
                    break;
                }

                $filter = new class($startRow, $chunkRowCount) implements IReadFilter {
                    public function __construct(private int $startRow, private int $chunkSize) {}
                    public function readCell($column, $row, $worksheetName = ''): bool
                    {
                        return $row >= $this->startRow && $row < ($this->startRow + $this->chunkSize);
                    }
                };

                if (method_exists($reader, 'setReadFilter')) {
                    $reader->setReadFilter($filter);
                }

                $spreadsheet = $reader->load($excelPath);
                $worksheet = $spreadsheet->getSheetByName($sheetName) ?? $spreadsheet->getSheet(0);

                $endRow = min($sheetRowCount, $startRow + $chunkRowCount - 1);
                $processedThisChunk = 0;

                for ($rowIndex = $startRow; $rowIndex <= $endRow; $rowIndex++) {
                    $rowValues = [];
                    $isEmpty = true;

                    foreach ($orderedColumns as $dbColumn) {
                        $colIndex = $columnIndexes[$dbColumn] ?? null;
                        if ($colIndex === null) {
                            $rowValues[] = null;
                            continue;
                        }

                        $cellValue = $worksheet->getCellByColumnAndRow($colIndex, $rowIndex)->getValue();

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
                    $rowValues[] = $nowStringSanitized;
                    $rowValues[] = $nowStringSanitized;

                    if ($useBulkInsert) {
                        fputcsv($csvHandle, $rowValues, $bulkDelimiter);
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
                    $processedThisChunk++;

                    if ($rowsSinceLog >= 5000) {
                        Log::info("Upload progress: {$totalRows} rows processed so far (chunk size {$chunkSize})");
                        $rowsSinceLog = 0;
                    }
                }

                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                gc_collect_cycles();

                if ($processedThisChunk === 0) {
                    // No data in this chunk; stop early
                    break;
                }

                $startRow += $chunkRowCount;
            }

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
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data valid yang dapat diimpor dari file.'
                ]);
            }

            // SQL Server bulk insert from CSV if enabled
            if ($useBulkInsert && $csvPath) {
                $pathForSql = str_replace('\\', '\\\\', $csvPath);
                $pathForSql = str_replace("'", "''", $pathForSql);
                // Use BULK INSERT without column list; staging table is built to match CSV column order
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
            }

            Log::info('Total rows to process: ' . $totalRows . " | chunks: {$chunksInserted} | chunkSize: {$chunkSize}");
            
            // Step 3: Atomic transaction to sync from staging to main table
            Log::info("Step 3: Starting atomic transaction to sync data");
            
            $driver = DB::connection()->getDriverName();
            DB::beginTransaction();
            
            try {
                if ($uploadMode === 'append') {
                    // APPEND MODE: Insert all staging rows as-is (with safe conversions)
                    Log::info("APPEND MODE: Inserting data without deletion for table '{$mainTableName}'");

                    $columns = array_merge(
                        array_values($columnMapping),
                        $hasPeriodDate ? ['period_date'] : [],
                        ['created_at', 'updated_at']
                    );

                    $columnTypes = [];
                    foreach ($columns as $col) {
                        try {
                            $columnTypes[$col] = $schema->getColumnType($mainTableName, $col);
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

                    DB::table($mainTableName)->insertUsing(
                        $columns,
                        DB::table($stagingTableName)->select($selects)
                    );

                    $message = $totalRows . " baris data berhasil ditambahkan ke tabel '{$mainTableName}' (Mode Append).";

                } elseif ($uploadMode === 'strict') {
                    // STRICT MODE: Delete data with same period_date, then insert all from staging
                    Log::info("STRICT MODE: Deleting data with period_date = '{$periodDate}' from main table '{$mainTableName}'");
                    
                    $columns = array_merge(
                        array_values($columnMapping),
                        $hasPeriodDate ? ['period_date'] : [],
                        ['created_at', 'updated_at']
                    );
                    
                    // Remove existing rows for the same period and replace with staging data
                    if ($hasPeriodDate) {
                        DB::table($mainTableName)
                            ->whereDate('period_date', $periodDate)
                            ->delete();
                    } else {
                        // Tidak ada period_date: hapus seluruh tabel (strict = replace all)
                        DB::table($mainTableName)->delete();
                    }

                    // If table becomes empty, reset identity so IDs start from 1 on next insert
                    $remainingRows = DB::table($mainTableName)->count();
                    if ($remainingRows === 0) {
                        $this->resetIdentity($mainTableName);
                        Log::info("STRICT MODE: Identity reset because table '{$mainTableName}' kosong setelah delete");
                    }

                    // Build safe select with TRY_CONVERT for numeric/date columns to avoid type errors on legacy DB
                    $columnTypes = [];
                    foreach ($columns as $col) {
                        try {
                            $columnTypes[$col] = $schema->getColumnType($mainTableName, $col);
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

                    DB::table($mainTableName)->insertUsing(
                        $columns,
                        DB::table($stagingTableName)->select($selects)
                    );
                    
                    Log::info("STRICT MODE: Successfully replaced data for period {$periodDate}");
                    $message = $totalRows . " baris data berhasil diimpor ke tabel '{$mainTableName}' (Mode Strict: Data period {$periodDate} di-replace).";
                    
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
            
            // Cleanup: Drop staging table if it exists
            $this->dropStagingTable($stagingTableName, $connection, true);

            if ($csvPath && file_exists($csvPath)) {
                @unlink($csvPath);
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
}
