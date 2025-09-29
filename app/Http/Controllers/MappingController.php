<?php

namespace App\Http\Controllers;

use App\Models\MappingColumn;
use App\Models\MappingIndex;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Imports\HeadingRowImport;

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
        // 1. Validasi input dan ambil data dari session
        $request->validate(['mappings' => 'required|array']);
        $mappingData = $request->session()->get('mapping_data');
        $user = auth()->user();

        // Redirect jika session hilang atau pengguna tidak punya divisi
        if (!$mappingData || !$user->division_id) {
            return redirect()->route('mapping.register.form')->withErrors(['session' => 'Sesi tidak valid atau Anda tidak terdaftar di divisi manapun.']);
        }

        // 2. Gunakan Database Transaction
        DB::transaction(function () use ($request, $mappingData, $user) {
            // 2a. Buat record induk di tabel `mapping_indices`
            $mappingIndex = MappingIndex::create([
                'division_id' => $user->division_id,
                'name' => $mappingData['name'],
                'original_headers' => $mappingData['excel_headers'],
            ]);

            // 2b. Loop dan simpan setiap pemetaan kolom
            foreach ($request->input('mappings') as $excelHeader => $dbColumn) {
                // Hanya simpan jika pengguna memilih kolom database (bukan '-- Jangan Simpan --')
                if (!empty($dbColumn)) {
                    MappingColumn::create([
                        'mapping_index_id' => $mappingIndex->id,
                        'excel_column' => $excelHeader,
                        'database_column' => $dbColumn,
                    ]);
                }
            }
        });

        // 3. Bersihkan session dan file temporer
        $request->session()->forget('mapping_data');
        Storage::delete($mappingData['file_path']);

        // 4. Redirect dengan pesan sukses
        return redirect()->route('dashboard')->with('success', 'Aturan mapping baru berhasil disimpan!');
    }
}