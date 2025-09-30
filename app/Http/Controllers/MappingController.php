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
            'name' => 'required|string|max:255|unique:mapping_indices,code', // UBAH: code, bukan name
            'table_name' => 'required|string|regex:/^[a-z0-9_]+$/|unique:mapping_indices,table_name',
            'header_row' => 'required|integer|min:1',
            'mappings' => 'required|array|min:1',
            'mappings.*.excel_column' => 'required|string|distinct|max:10',
            'mappings.*.database_column' => ['required', 'string', 'distinct', 'regex:/^[a-z0-9_]+$/', Rule::notIn(['id'])],
        ], [
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
            Schema::create($tableName, function (Blueprint $table) use ($validated) {
                $table->id();
                foreach ($validated['mappings'] as $mapping) {
                    $table->text($mapping['database_column'])->nullable();
                }
                $table->timestamps();
            });
            Log::info("Tabel '{$tableName}' berhasil dibuat.");

            $mappingIndex = MappingIndex::create([
                'code' => strtolower(str_replace(' ', '_', $validated['name'])), // UBAH: code
                'description' => $validated['name'], // UBAH: description
                'table_name' => $tableName,
                'header_row' => $validated['header_row'],
                'division_id' => Auth::user()->division_id,
            ]);
            Log::info("Format berhasil disimpan di mapping_indices dengan ID: {$mappingIndex->id}");

            foreach ($validated['mappings'] as $mapping) {
                MappingColumn::create([
                    'mapping_index_id' => $mappingIndex->id,
                    'excel_column_index' => strtoupper($mapping['excel_column']), // UBAH: excel_column_index
                    'table_column_name' => $mapping['database_column'], // UBAH: table_column_name
                    'data_type' => 'string', // TAMBAH
                    'is_required' => false, // TAMBAH
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
    private function generatePreviewHtml($mapping, $headers, $previewRows, $mappingRules): string
    {
        $html = '<div class="space-y-4">';
        
        // Info section
        $html .= '<div class="bg-blue-50 border border-blue-200 rounded p-3">';
        $html .= '<p class="text-sm"><strong>Format:</strong> ' . htmlspecialchars($mapping->description) . '</p>';
        $html .= '<p class="text-sm"><strong>Tabel Tujuan:</strong> ' . htmlspecialchars($mapping->table_name) . '</p>';
        $html .= '<p class="text-sm"><strong>Baris Header:</strong> ' . $mapping->header_row . '</p>';
        $html .= '</div>';
        
        // Mapping section
        $html .= '<div>';
        $html .= '<h4 class="font-medium mb-2">Mapping Kolom:</h4>';
        $html .= '<div class="grid grid-cols-2 gap-2 text-sm">';
        foreach ($mappingRules as $rule) {
            $html .= '<div class="flex items-center space-x-2 bg-gray-50 p-2 rounded">';
            $html .= '<span class="font-mono bg-yellow-100 px-2 py-1 rounded text-xs">' . htmlspecialchars($rule->excel_column_index) . '</span>';
            $html .= '<span>→</span>';
            $html .= '<span class="font-mono bg-green-100 px-2 py-1 rounded text-xs">' . htmlspecialchars($rule->table_column_name) . '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
        
        // Preview table
        $html .= '<div>';
        $html .= '<h4 class="font-medium mb-2">Preview Data (5 baris pertama):</h4>';
        $html .= '<div class="overflow-x-auto border rounded max-h-96">';
        $html .= '<table class="min-w-full divide-y divide-gray-200 text-sm">';
        
        // Table header
        $html .= '<thead class="bg-gray-50 sticky top-0"><tr>';
        $html .= '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>';
        foreach ($headers as $header) {
            $html .= '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr></thead>';
        
        // Table body
        $html .= '<tbody class="bg-white divide-y divide-gray-200">';
        $index = 1;
        foreach ($previewRows as $row) {
            $html .= '<tr class="hover:bg-gray-50">';
            $html .= '<td class="px-3 py-2 whitespace-nowrap text-gray-500">' . $index . '</td>';
            foreach ($row as $cell) {
                $html .= '<td class="px-3 py-2 whitespace-nowrap">' . htmlspecialchars($cell ?? '') . '</td>';
            }
            $html .= '</tr>';
            $index++;
        }
        $html .= '</tbody>';
        
        $html .= '</table>';
        $html .= '</div>';
        $html .= '<p class="text-xs text-gray-500 mt-2">* Hanya menampilkan 5 baris pertama sebagai preview</p>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
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