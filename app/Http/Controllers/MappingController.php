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

            // Simpan format ke mapping_indices dengan struktur BARU
            $mappingIndex = MappingIndex::create([
                'code' => strtolower(str_replace(' ', '_', $validated['name'])),
                'description' => $validated['name'],
                'table_name' => $tableName,
                'header_row' => $validated['header_row'],
                'division_id' => Auth::user()->division_id,
            ]);
            Log::info("Format berhasil disimpan di mapping_indices dengan ID: {$mappingIndex->id}");

            // Simpan mapping kolom dengan NAMA KOLOM BARU
            foreach ($validated['mappings'] as $mapping) {
                MappingColumn::create([
                    'mapping_index_id' => $mappingIndex->id,
                    'excel_column_index' => strtoupper($mapping['excel_column']),  // PERBAIKAN: gunakan excel_column_index
                    'table_column_name' => $mapping['database_column'],             // PERBAIKAN: gunakan table_column_name
                    'data_type' => 'string',
                    'is_required' => false,
                ]);
            }
            Log::info("Pemetaan kolom berhasil disimpan.");

            DB::commit();
            return redirect()->route('dashboard')->with('success', "Format '{$validated['name']}' berhasil disimpan dan tabel '{$tableName}' telah dibuat!");

        } catch (\Exception $e) {
            DB::rollBack();
            Schema::dropIfExists($tableName);
            Log::error('Gagal membuat tabel/format: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
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
            $headerRow = $mapping->header_row - 1; // Convert to 0-based index
            $headers = $excelData->get($headerRow);
            
            // Ambil preview data (5 rows setelah header)
            $previewRows = $excelData->slice($headerRow + 1, 5);
            
            // Get mapping rules
            $mappingRules = $mapping->columns;

            // Generate HTML langsung
            $html = $this->generatePreviewHtml($mapping, $headers, $previewRows, $mappingRules);

            return response()->json([
                'success' => true,
                'html' => $html
            ]);

        } catch (\Exception $e) {
            Log::error('Preview error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Generate HTML for preview
     */
    /**
 * Generate HTML for interactive preview with checkboxes and dropdowns
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
     * Upload data - Process actual upload
     */
    public function uploadData(Request $request): JsonResponse
    {
        Log::info('=== MEMULAI UPLOAD DATA ===');
        
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

            $tableName = $mapping->table_name;
            $headerRow = $mapping->header_row;
            
            Log::info('Mapping info:', [
                'id' => $mapping->id,
                'name' => $mapping->name,
                'table_name' => $tableName,
                'header_row' => $headerRow
            ]);

            if (!$tableName || !Schema::hasTable($tableName)) {
                Log::error("Tabel tidak ditemukan: {$tableName}");
                return response()->json([
                    'success' => false,
                    'message' => "Tabel '{$tableName}' tidak ditemukan di database."
                ]);
            }

            // Get mapping rules: excel_column_index => table_column_name
            $mappingRules = $mapping->columns->pluck('table_column_name', 'excel_column_index')->toArray();
            Log::info('Mapping rules:', $mappingRules);

            // Read Excel
            $excelData = Excel::toCollection(null, $request->file('data_file'))->first();
            Log::info('Total rows in Excel: ' . $excelData->count());

            // Get data rows (skip header)
            $dataRows = $excelData->slice($headerRow);
            Log::info('Data rows after header: ' . $dataRows->count());

            $dataToInsert = [];
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
                foreach ($mappingRules as $excelColumn => $dbColumn) {
                    $columnIndex = ord(strtoupper($excelColumn)) - ord('A');
                    $value = $row[$columnIndex] ?? null;
                    $rowData[$dbColumn] = $value;
                    
                    Log::debug("Row {$rowNumber}: Col {$excelColumn}(idx:{$columnIndex}) -> {$dbColumn} = " . var_export($value, true));
                }

                if (!empty($rowData)) {
                    $rowData['created_at'] = now();
                    $rowData['updated_at'] = now();
                    $dataToInsert[] = $rowData;
                }
                
                $rowNumber++;
            }

            Log::info('Total rows to insert: ' . count($dataToInsert));

            if (empty($dataToInsert)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data valid yang dapat diimpor dari file.'
                ]);
            }

            // Insert to database
            DB::table($tableName)->insert($dataToInsert);
            
            Log::info("=== BERHASIL INSERT " . count($dataToInsert) . " ROWS ===");

            return response()->json([
                'success' => true,
                'message' => count($dataToInsert) . " baris data berhasil diimpor ke tabel '{$tableName}'."
            ]);

        } catch (\Exception $e) {
            Log::error('Upload error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal upload data: ' . $e->getMessage()
            ]);
        }
    }
}