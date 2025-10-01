<?php

namespace App\Http\Controllers;

use App\Models\MappingColumn;
use App\Models\MappingIndex;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

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
        
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:mapping_indices,code',
            'table_name' => 'required|string|regex:/^[a-z0-9_]+$/|unique:mapping_indices,table_name',
            'header_row' => 'required|integer|min:1',
            'mappings' => 'required|array|min:1',
            'mappings.*.excel_column' => 'required|string|distinct|max:10',
            'mappings.*.database_column' => ['required', 'string', 'distinct', 'regex:/^[a-z0-9_]+$/', Rule::notIn(['id'])],
            'mappings.*.is_unique_key' => 'nullable|in:0,1,true,false',
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
                    $table->text($mapping['database_column'])->nullable();
                }
                $table->timestamps();
                
                // Add unique constraint if there are unique key columns
                if (!empty($uniqueKeyColumns)) {
                    $table->unique($uniqueKeyColumns, $tableName . '_unique_key');
                    Log::info("Unique constraint created on columns: " . implode(', ', $uniqueKeyColumns));
                }
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
            return redirect()->route('dashboard')->with('success', "Format '{$validated['name']}' berhasil disimpan dan tabel '{$tableName}' telah dibuat!");

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
    public function index(): View
    {
        $user = Auth::user();
        
        $query = MappingIndex::with('columns', 'division');
        
        // Filter by division if not super-admin
        if (!$user->hasRole('super-admin')) {
            $query->where('division_id', $user->division_id);
        }
        
        $mappings = $query->orderBy('description')->get();
        
        // Get statistics for each mapping
        $mappings->each(function ($mapping) use ($user) {
            $tableName = $mapping->table_name;
            
            if (Schema::hasTable($tableName)) {
                $query = DB::table($tableName);
                
                // Filter by division if not super-admin
                if (!$user->hasRole('super-admin')) {
                    $actualTableColumns = DB::getSchemaBuilder()->getColumnListing($tableName);
                    if (in_array('division_id', $actualTableColumns)) {
                        $query->where('division_id', $user->division_id);
                    }
                }
                
                $mapping->row_count = $query->count();
            } else {
                $mapping->row_count = 0;
            }
        });
        
        return view('formats.index', [
            'mappings' => $mappings,
            'totalFormats' => $mappings->count(),
        ]);
    }

    /**
     * View data from a specific format/mapping table
     */
    public function viewData($mappingId): View
    {
        $user = Auth::user();
        $mapping = MappingIndex::with('columns')->findOrFail($mappingId);

        // Check permission
        if (!$user->hasRole('super-admin') && $mapping->division_id !== $user->division_id) {
            abort(403, 'Anda tidak memiliki akses untuk melihat data format ini.');
        }

        $tableName = $mapping->table_name;
        
        // Get column mapping sorted by Excel column order
        $columnMapping = $mapping->columns->sortBy(function($col) {
            return ord($col->excel_column_index);
        })->pluck('table_column_name', 'excel_column_index')->toArray();

        if (empty($columnMapping)) {
            return back()->with('error', 'Tidak ada kolom yang di-mapping untuk format ini.');
        }

        // Get actual table columns
        $actualTableColumns = DB::getSchemaBuilder()->getColumnListing($tableName);
        $validColumns = array_intersect(array_values($columnMapping), $actualTableColumns);

        if (empty($validColumns)) {
            return back()->with('error', 'Konfigurasi mapping tidak sesuai dengan skema tabel.');
        }

        // Build query
        $query = DB::table($tableName)->select(array_merge(['id'], $validColumns, ['created_at', 'updated_at']));
        
        // Filter by division if not super-admin
        if (!$user->hasRole('super-admin')) {
            if (in_array('division_id', $actualTableColumns)) {
                $query->where('division_id', $user->division_id);
            }
        }
        
        // Paginate results
        $data = $query->orderBy('id', 'desc')->paginate(50);

        return view('view_data', [
            'mapping' => $mapping,
            'columns' => $validColumns,
            'data' => $data,
            'columnMapping' => $columnMapping,
        ]);
    }

    /**
     * Preview upload - Menampilkan preview data dan mapping
     */
    public function showUploadPreview(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'data_file' => ['required', File::types(['xlsx', 'xls'])],
                'mapping_id' => ['required', 'integer', Rule::exists('mapping_indices', 'id')],
            ]);

            $mapping = MappingIndex::with('columns')->find($validated['mapping_id']);
            
            if (!$mapping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format tidak ditemukan.'
                ]);
            }

            // Baca file Excel
            $excelData = Excel::toCollection(null, $request->file('data_file'))->first();
            
            // Ambil header row
            $headerRow = $mapping->header_row - 1;
            $headers = $excelData->get($headerRow);
            
            // Ambil preview data (5 rows setelah header)
            $previewRows = $excelData->slice($headerRow + 1, 5);
            
            // Get mapping rules
            $mappingRules = $mapping->columns;

            // Generate HTML
            $html = $this->generatePreviewHtml($mapping, $headers, $previewRows, $mappingRules);

            return response()->json([
                'success' => true,
                'html' => $html
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
        $html .= '<span class="font-semibold text-gray-900">Strict (Hapus Semua & Insert Baru)</span>';
        $html .= '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Hati-hati</span>';
        $html .= '</div>';
        $html .= '<p class="text-sm text-gray-600 mt-1">Hapus semua data lama di tabel, lalu insert semua data baru dari file</p>';
        $html .= '<p class="text-xs text-red-600 mt-1 font-medium">⚠️ Semua data lama akan dihapus permanen!</p>';
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
     * Upload data - Process actual upload with STAGING TABLE pattern
     */
    public function uploadData(Request $request): JsonResponse
    {
        Log::info('=== MEMULAI UPLOAD DATA DENGAN STAGING TABLE PATTERN ===');
        
        $stagingTableName = null;
        
        try {
            $validated = $request->validate([
                'data_file' => ['required', File::types(['xlsx', 'xls'])],
                'mapping_id' => ['required', 'integer', Rule::exists('mapping_indices', 'id')],
                'selected_columns' => ['nullable', 'string'],
                'upload_mode' => ['required', 'string', Rule::in(['upsert', 'strict'])],
            ]);

            $mapping = MappingIndex::with('columns')->find($validated['mapping_id']);
            
            if (!$mapping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format tidak ditemukan.'
                ]);
            }

            $mainTableName = $mapping->table_name;
            $headerRow = $mapping->header_row;
            $uploadMode = $validated['upload_mode'];
            
            Log::info('Mapping info:', [
                'id' => $mapping->id,
                'description' => $mapping->description,
                'table_name' => $mainTableName,
                'header_row' => $headerRow,
                'upload_mode' => $uploadMode,
            ]);

            if (!$mainTableName || !Schema::hasTable($mainTableName)) {
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

            // Validate upsert mode has unique keys
            if ($uploadMode === 'upsert' && empty($uniqueKeyColumns)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mode Upsert memerlukan minimal satu kolom yang ditandai sebagai kunci unik. Silakan pilih mode Strict atau atur kunci unik pada format.'
                ]);
            }

            // Create mapping array: excel_column_index => table_column_name
            $columnMapping = $mappingRules->pluck('table_column_name', 'excel_column_index')->toArray();
            Log::info('Column mapping:', $columnMapping);

            // Read Excel
            $excelData = Excel::toCollection(null, $request->file('data_file'))->first();
            Log::info('Total rows in Excel: ' . $excelData->count());

            // Get data rows (skip header)
            $dataRows = $excelData->slice($headerRow);
            Log::info('Data rows after header: ' . $dataRows->count());

            $dataToProcess = [];
            $rowNumber = $headerRow + 1;

            foreach ($dataRows as $row) {
                // Skip empty rows
                if ($row->filter()->isEmpty()) {
                    Log::debug("Row {$rowNumber}: Empty, skipped");
                    $rowNumber++;
                    continue;
                }

                $rowData = [];
                
                // Map each Excel column to database column
                foreach ($columnMapping as $excelColumn => $dbColumn) {
                    $columnIndex = ord(strtoupper($excelColumn)) - ord('A');
                    $value = $row[$columnIndex] ?? null;
                    $rowData[$dbColumn] = $value;
                    
                    Log::debug("Row {$rowNumber}: Col {$excelColumn}(idx:{$columnIndex}) -> {$dbColumn} = " . var_export($value, true));
                }

                if (!empty($rowData)) {
                    $rowData['created_at'] = now();
                    $rowData['updated_at'] = now();
                    $dataToProcess[] = $rowData;
                }
                
                $rowNumber++;
            }

            Log::info('Total rows to process: ' . count($dataToProcess));

            if (empty($dataToProcess)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data valid yang dapat diimpor dari file.'
                ]);
            }

            // ========================================
            // STAGING TABLE PATTERN IMPLEMENTATION
            // ========================================
            
            // Step 1: Create staging table with unique random name
            $stagingTableName = 'staging_' . $mainTableName . '_' . Str::random(8);
            Log::info("Step 1: Creating staging table: {$stagingTableName}");
            
            // Create staging table with identical structure INCLUDING unique constraint
            Schema::create($stagingTableName, function (Blueprint $table) use ($columnMapping, $uniqueKeyColumns) {
                $table->id();
                
                // Add all mapped columns as text/nullable (same as main table)
                foreach ($columnMapping as $dbColumn) {
                    $table->text($dbColumn)->nullable();
                }
                
                $table->timestamps();
                
                // Add unique constraint if there are unique key columns for UPSERT mode
                if (!empty($uniqueKeyColumns)) {
                    $table->unique($uniqueKeyColumns, 'staging_unique_key_' . Str::random(6));
                    Log::info("Staging table unique constraint created on: " . implode(', ', $uniqueKeyColumns));
                }
            });
            
            Log::info("Staging table '{$stagingTableName}' created successfully");
            
            // Step 2: Insert all data into staging table
            Log::info("Step 2: Inserting " . count($dataToProcess) . " rows into staging table");
            DB::table($stagingTableName)->insert($dataToProcess);
            Log::info("Data successfully inserted into staging table");
            
            // Step 3: Atomic transaction to sync from staging to main table
            Log::info("Step 3: Starting atomic transaction to sync data");
            
            DB::beginTransaction();
            
            try {
                if ($uploadMode === 'strict') {
                    // STRICT MODE: Truncate main table, then copy all from staging
                    Log::info("STRICT MODE: Truncating main table '{$mainTableName}'");
                    DB::table($mainTableName)->truncate();
                    
                    Log::info("Copying all data from staging to main table");
                    
                    // Build column list for INSERT SELECT
                    $columns = array_merge(['id'], array_values($columnMapping), ['created_at', 'updated_at']);
                    $columnsList = implode(', ', array_map(fn($col) => '"' . $col . '"', $columns));
                    
                    // Execute INSERT INTO ... SELECT
                    $sql = "INSERT INTO \"{$mainTableName}\" ({$columnsList}) 
                            SELECT {$columnsList} FROM \"{$stagingTableName}\"";
                    
                    DB::statement($sql);
                    
                    Log::info("STRICT MODE: Successfully copied all data from staging to main table");
                    $message = count($dataToProcess) . " baris data berhasil diimpor ke tabel '{$mainTableName}' (Mode Strict: Data lama dihapus).";
                    
                } else {
                    // UPSERT MODE: Use ON CONFLICT DO UPDATE
                    Log::info("UPSERT MODE: Using ON CONFLICT for unique keys: " . implode(', ', $uniqueKeyColumns));
                    
                    // Build columns list (excluding id for insert)
                    $dataColumns = array_values($columnMapping);
                    $allColumns = array_merge($dataColumns, ['created_at', 'updated_at']);
                    
                    // Build conflict target (unique key columns)
                    $conflictTarget = implode(', ', array_map(fn($col) => '"' . $col . '"', $uniqueKeyColumns));
                    
                    // Build UPDATE SET clause (all columns except unique keys)
                    $updateClauses = [];
                    foreach ($allColumns as $col) {
                        $updateClauses[] = "\"{$col}\" = EXCLUDED.\"{$col}\"";
                    }
                    $updateSet = implode(', ', $updateClauses);
                    
                    // Build column list for INSERT
                    $columnsList = implode(', ', array_map(fn($col) => '"' . $col . '"', $allColumns));
                    
                    // Execute INSERT ... ON CONFLICT DO UPDATE
                    $sql = "INSERT INTO \"{$mainTableName}\" ({$columnsList}) 
                            SELECT {$columnsList} FROM \"{$stagingTableName}\"
                            ON CONFLICT ({$conflictTarget}) DO UPDATE SET {$updateSet}";
                    
                    DB::statement($sql);
                    
                    Log::info("UPSERT MODE: Successfully synced data using ON CONFLICT");
                    $message = count($dataToProcess) . " baris data berhasil diproses ke tabel '{$mainTableName}' (Mode Upsert).";
                }
                
                // Commit transaction
                DB::commit();
                Log::info("=== TRANSAKSI BERHASIL DI-COMMIT ===");
                
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('=== TRANSAKSI DI-ROLLBACK ===');
                Log::error('Sync error: ' . $e->getMessage());
                throw $e; // Re-throw to outer catch block
            }
            
            // Step 4: Cleanup - Drop staging table
            Log::info("Step 4: Cleanup - Dropping staging table '{$stagingTableName}'");
            Schema::dropIfExists($stagingTableName);
            Log::info("Staging table dropped successfully");
            
            return response()->json([
                'success' => true,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            Log::error('=== UPLOAD FAILED ===');
            Log::error('Upload error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            Log::error('File: ' . $e->getFile());
            Log::error('Line: ' . $e->getLine());
            
            // Cleanup: Drop staging table if it exists
            if ($stagingTableName && Schema::hasTable($stagingTableName)) {
                Log::info("Emergency cleanup: Dropping staging table '{$stagingTableName}'");
                try {
                    Schema::dropIfExists($stagingTableName);
                    Log::info("Staging table dropped in error cleanup");
                } catch (\Exception $cleanupError) {
                    Log::error("Failed to drop staging table in cleanup: " . $cleanupError->getMessage());
                }
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal upload data: ' . $e->getMessage()
            ]);
        }
    }
}