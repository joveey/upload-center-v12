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
        // Metode ini hanya menampilkan view, tidak perlu mengirim data apa pun.
        return view('register_form');
    }

    /**
     * Memproses formulir: MEMBUAT TABEL BARU di database dan menyimpan aturan pemetaan.
     */
    public function processRegisterForm(Request $request): RedirectResponse
    {
        Log::info('Memulai proses pendaftaran format & pembuatan tabel baru.');
        
        // 1. Validasi input, termasuk nama tabel dan kolom dari pengguna
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:mapping_indices,name',
            'table_name' => 'required|string|regex:/^[a-z0-9_]+$/|unique:mapping_indices,table_name',
            'header_row' => 'required|integer|min:1',
            'mappings' => 'required|array|min:1',
            'mappings.*.excel_column' => 'required|string|distinct|max:10',
            // Melarang pengguna membuat kolom 'id' secara manual
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
            // 2. Membuat tabel baru di database sesuai definisi pengguna
            Schema::create($tableName, function (Blueprint $table) use ($validated) {
                $table->id(); // Kolom ID utama dibuat otomatis
                foreach ($validated['mappings'] as $mapping) {
                    // Semua kolom dibuat sebagai TEXT untuk fleksibilitas
                    $table->text($mapping['database_column'])->nullable();
                }
                $table->timestamps(); // Kolom created_at dan updated_at
            });
            Log::info("Tabel '{$tableName}' berhasil dibuat.");

            // 3. Simpan informasi format ini ke database
            $mappingIndex = MappingIndex::create([
                'name' => $validated['name'],
                'table_name' => $tableName,
                'header_row' => $validated['header_row'],
                'division_id' => Auth::user()->division_id,
            ]);
            Log::info("Format berhasil disimpan di mapping_indices dengan ID: {$mappingIndex->id}");

            // 4. Simpan detail pemetaan kolom
            foreach ($validated['mappings'] as $mapping) {
                MappingColumn::create([
                    'mapping_index_id' => $mappingIndex->id,
                    'excel_column' => strtoupper($mapping['excel_column']),
                    'database_column' => $mapping['database_column'],
                ]);
            }
            Log::info("Pemetaan kolom berhasil disimpan.");

            DB::commit();
            return redirect()->route('dashboard')->with('success', "Format '{$validated['name']}' berhasil disimpan dan tabel '{$tableName}' telah dibuat!");

        } catch (\Exception $e) {
            DB::rollBack();
            Schema::dropIfExists($tableName); // Hapus tabel jika terjadi error
            Log::error('Gagal membuat tabel/format: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Mengunggah data ke tabel yang benar sesuai format yang dipilih.
     */
    public function uploadData(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'data_file' => ['required', File::types(['xlsx', 'xls'])],
            'mapping_id' => ['required', 'integer', Rule::exists('mapping_indices', 'id')],
        ]);

        $mapping = MappingIndex::with('columns')->find($validated['mapping_id']);
        $tableName = $mapping->table_name;
        $headerRow = $mapping->header_row;

        if (!$tableName || !Schema::hasTable($tableName)) {
            return back()->with('error', "Tabel tujuan '{$tableName}' tidak ditemukan. Format mungkin rusak.");
        }

        $mappingRules = $mapping->columns->pluck('database_column', 'excel_column')->toArray();
        $excelData = Excel::toCollection(null, $request->file('data_file'))->first();
        $dataRows = $excelData->slice($headerRow - 1);
        
        $dataToInsert = [];
        foreach ($dataRows as $row) {
            $rowData = [];
            if ($row->filter()->isEmpty()) continue;

            foreach ($mappingRules as $excelColumn => $dbColumn) {
                $columnIndex = ord(strtoupper($excelColumn)) - ord('A');
                $rowData[$dbColumn] = $row[$columnIndex] ?? null;
            }
            
            if (!empty($rowData)) {
                $rowData['created_at'] = now();
                $rowData['updated_at'] = now();
                $dataToInsert[] = $rowData;
            }
        }

        try {
            if (!empty($dataToInsert)) {
                DB::table($tableName)->insert($dataToInsert);
                return redirect()->route('dashboard')->with('success', count($dataToInsert) . " baris data berhasil diimpor ke tabel '$tableName'.");
            } else {
                return back()->with('error', 'Tidak ada data valid yang bisa diimpor dari file.');
            }
        } catch (\Exception $e) {
             Log::error("Gagal impor ke tabel '{$tableName}': " . $e->getMessage());
            return back()->with('error', "Gagal memasukkan data ke tabel. Error: " . $e->getMessage());
        }
    }
}