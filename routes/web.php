<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MappingController;
use App\Http\Controllers\ProfileController;
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

    // Alur Cepat -> Memproses unggahan data setelah diseleksi di modal
    Route::post('/upload', [MappingController::class, 'uploadData'])
        ->middleware('can:upload data')
        ->name('upload.process');

    // [BARU] Route untuk mengambil konten pratinjau untuk ditampilkan di modal
    Route::post('/upload-preview', [MappingController::class, 'showUploadPreview'])
        ->middleware('can:upload data')
        ->name('upload.preview');


    // Alur Cerdas -> Mendaftarkan format laporan baru
    Route::middleware('can:register format')->group(function () {
        Route::get('/register-format', [MappingController::class, 'showRegisterForm'])->name('mapping.register.form');
        Route::post('/register-format', [MappingController::class, 'processRegisterForm'])->name('mapping.register.process');
        Route::get('/register-format/map', [MappingController::class, 'showMapForm'])->name('mapping.map.form');
        Route::post('/register-format/map', [MappingController::class, 'storeMapping'])->name('mapping.map.store');
        
        // Route ini dari alur registrasi asli, untuk menampilkan pratinjau saat membuat format
        // Kita biarkan saja untuk menjaga fungsionalitas asli
        Route::post('/register-format/preview', [MappingController::class, 'previewUpload'])->name('mapping.preview');
    });
});

// Memuat rute-rute autentikasi bawaan Breeze (login, register, dll.)
require __DIR__.'/auth.php';