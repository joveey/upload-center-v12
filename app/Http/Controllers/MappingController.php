<?php

namespace App\Http\Controllers;

use App\Imports\DataImporter;
use App\Models\MappingColumn;
use App\Models\MappingIndex;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Imports\HeadingRowImport;
use Maatwebsite\Excel\Validators\ValidationException;

class MappingController extends Controller
{
    public function showRegisterForm(): View
    {
        return view('register_form');
    }

    public function previewUpload(Request $request): View
    {
        $request->validate([
            'excel_file' => ['required', File::types(['xlsx', 'xls'])],
        ]);

        $rows = Excel::toCollection(null, $request->file('excel_file'))->first()->take(20);
        return view('partials.preview-table', ['rows' => $rows]);
    }

    public function processRegisterForm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'excel_file' => ['required', File::types(['xlsx', 'xls'])],
            'header_row' => 'required|integer|min:1',
        ]);
        $filePath = $request->file('excel_file')->store('temp');

        if (!$filePath) {
            return back()->withErrors(['excel_file' => 'File tidak berhasil diunggah. Silakan coba lagi.']);
        }

        try {
            $headings = (new HeadingRowImport($validated['header_row']))->toArray($filePath);
            $excelHeaders = $headings[0][0];
            $excelHeaders = array_filter($excelHeaders, fn($value) => !is_null($value) && $value !== '');

        } catch (\Exception $e) {
            return back()->withErrors(['excel_file' => 'Gagal membaca file Excel. Pastikan file tidak rusak dan nomor baris header benar.']);
        }

        $request->session()->put('mapping_data', [
            'name' => $validated['name'],
            'header_row' => $validated['header_row'],
            'file_path' => $filePath,
            'excel_headers' => $excelHeaders,
            'destination_table' => 'spotify_users',
        ]);

        return redirect()->route('mapping.map.form');
    }

    /**
     * Menampilkan form untuk memetakan kolom Excel ke kolom database.
     */
    public function showMapForm(Request $request): View|RedirectResponse
    {
        $mappingData = $request->session()->get('mapping_data');
        if (!$mappingData) {
            return redirect()->route('mapping.register.form')->withErrors(['session' => 'Sesi Anda telah berakhir, silakan mulai lagi.']);
        }
        $destinationTable = $mappingData['destination_table'];
        
        // 1. Ambil semua kolom dari tabel
        $allDatabaseColumns = Schema::getColumnListing($destinationTable);
        
        // 2. Tentukan kolom mana yang ingin disembunyikan
        $unwantedColumns = ['id', 'division_id', 'created_at', 'updated_at'];
        
        // 3. Filter array untuk mendapatkan hanya kolom yang bisa dipetakan
        $databaseColumns = array_diff($allDatabaseColumns, $unwantedColumns);
        
        return view('map_form', [
            'excelHeaders' => $mappingData['excel_headers'],
            'databaseColumns' => $databaseColumns, // Kirim data yang sudah difilter
            'formatName' => $mappingData['name'],
        ]);
    }

    public function storeMapping(Request $request): RedirectResponse
    {
        $request->validate(['mappings' => 'required|array']);
        $mappingData = $request->session()->get('mapping_data');
        $user = auth()->user();
        if (!$mappingData || !$user->division_id) {
            return redirect()->route('mapping.register.form')->withErrors(['session' => 'Sesi tidak valid atau Anda tidak terdaftar di divisi manapun.']);
        }
        DB::transaction(function () use ($request, $mappingData, $user) {
            $mappingIndex = MappingIndex::create([
                'division_id' => $user->division_id,
                'name' => $mappingData['name'],
                'original_headers' => $mappingData['excel_headers'],
            ]);
            foreach ($request->input('mappings') as $excelHeader => $dbColumn) {
                if (!empty($dbColumn)) {
                    MappingColumn::create([
                        'mapping_index_id' => $mappingIndex->id,
                        'excel_column' => $excelHeader,
                        'database_column' => $dbColumn,
                    ]);
                }
            }
        });
        $request->session()->forget('mapping_data');
        Storage::delete($mappingData['file_path']);
        return redirect()->route('dashboard')->with('success', 'Aturan mapping baru berhasil disimpan!');
    }

    public function uploadData(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'data_file' => ['required', File::types(['xlsx', 'xls'])],
            'mapping_id' => [
                'required', 'integer',
                Rule::exists('mapping_indices', 'id')->where('division_id', auth()->user()->division_id),
            ],
        ]);
        $mapping = MappingIndex::with('columns')->find($validated['mapping_id']);
        $mappingRules = $mapping->columns->pluck('database_column', 'excel_column')->toArray();
        try {
            $importer = new DataImporter($mappingRules);
            Excel::import($importer, $request->file('data_file'));
            $successMessage = 'Data dari file berhasil diimpor!';
            if ($importer->getFailures()->isNotEmpty()) {
                $successMessage .= ' Namun, ' . $importer->getFailures()->count() . ' baris data terdeteksi tidak valid dan dilewati.';
            }
            return redirect()->route('dashboard')->with('success', $successMessage);
        } catch (ValidationException $e) {
            return redirect()->route('dashboard')->withErrors(['import_error' => 'Validasi data gagal pada beberapa baris. Tidak ada data yang diimpor.']);
        } catch (\Exception $e) {
            return redirect()->route('dashboard')->withErrors(['import_error' => 'Gagal mengimpor file. Pastikan format file sesuai dengan aturan yang dipilih dan tidak rusak.']);
        }
    }
}