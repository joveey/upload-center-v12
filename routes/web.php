<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MappingController; // Pastikan ini ada
use App\Http\Controllers\ProfileController;
// use App\Http\Controllers\UploadController; // Hapus atau komentari baris ini karena sudah tidak dipakai
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Rute Halaman Awal
Route::get('/', function () {
    return view('welcome');
});

// Rute Dashboard Utama (memerlukan login)
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

// Grup Rute yang Memerlukan Login
Route::middleware('auth')->group(function () {
    // Rute untuk Manajemen Profil Pengguna
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /*
    |--------------------------------------------------------------------------
    | Rute Fungsional Aplikasi
    |--------------------------------------------------------------------------
    */

    // Alur Cepat -> Memproses unggahan data menggunakan format yang ada
    Route::post('/upload', [MappingController::class, 'uploadData'])
        ->middleware('can:upload data')
        ->name('upload.process');

    // Alur Cerdas -> Mendaftarkan format laporan baru
    Route::middleware('can:register format')->group(function () {
        Route::get('/register-format', [MappingController::class, 'showRegisterForm'])->name('mapping.register.form');
        Route::post('/register-format', [MappingController::class, 'processRegisterForm'])->name('mapping.register.process');
        Route::get('/register-format/map', [MappingController::class, 'showMapForm'])->name('mapping.map.form');
        Route::post('/register-format/map', [MappingController::class, 'storeMapping'])->name('mapping.map.store');
        Route::post('/register-format/preview', [MappingController::class, 'previewUpload'])->name('mapping.preview');
    });
});

    

// Memuat rute-rute autentikasi bawaan Breeze (login, register, dll.)
require __DIR__.'/auth.php';