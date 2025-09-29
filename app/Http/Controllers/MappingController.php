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
    /**
     * Menampilkan form untuk mendaftarkan format file baru.
     */
    public function showRegisterForm(): View
    {
        return view('register_form');
    }

    /**
     * Memproses file yang diunggah, mengekstrak header, dan menyimpannya di session.
     */
    public function processRegisterForm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'excel_file' => ['required', File::types(['xlsx', 'xls'])],
            'header_row' => 'required|integer|min:1',
        ]);

        $filePath = $request->file('excel_file')->store('temp');

        try {
            $headings = (new HeadingRowImport($validated['header_row']))->toArray($filePath);
            $excelHeaders = $headings[0][0];
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
        $databaseColumns = Schema::getColumnListing($destinationTable);

        return view('map_form', [
            'excelHeaders' => $mappingData['excel_headers'],
            'databaseColumns' => $databaseColumns,
            'formatName' => $mappingData['name'],
        ]);
    }

    /**
     * Menyimpan aturan pemetaan yang telah dibuat ke database.
     */
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

    /**
     * Memproses file yang diunggah menggunakan format yang sudah ada.
     */
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
            // Jika ada baris yang dilewati, tambahkan informasi ke pesan sukses
            if ($importer->getFailures()->isNotEmpty()) {
                $successMessage .= ' Namun, ' . $importer->getFailures()->count() . ' baris data terdeteksi tidak valid dan dilewati.';
            }
            return redirect()->route('dashboard')->with('success', $successMessage);

        } catch (ValidationException $e) {
            // Tangkap error validasi spesifik dari Maatwebsite/Excel
            $failures = $e->failures(); // Dapatkan detail baris mana yang error dan kenapa
            // Kita bisa teruskan $failures ini ke view jika ingin menampilkannya secara detail
            return redirect()->route('dashboard')->withErrors(['import_error' => 'Validasi data gagal pada beberapa baris. Tidak ada data yang diimpor.']);
        
        } catch (\Exception $e) {
            // Tangkap error umum lainnya (file rusak, dll)
            return redirect()->route('dashboard')->withErrors(['import_error' => 'Gagal mengimpor file. Pastikan format file sesuai dengan aturan yang dipilih dan tidak rusak.']);
        }
    }
}