<?php
// routes/web.php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MappingController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Public Welcome Page (accessible by everyone)
Route::get('/', function () {
    return view('auth.login');
})->name('Login Page');

// Dashboard (only for authenticated users)
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Export route
    Route::get('/export/{mapping}', [App\Http\Controllers\ExportController::class, 'export'])
        ->name('export.data');
    
    // View data route - NEW
    Route::get('/mapping/{mapping}/view', [MappingController::class, 'viewData'])
        ->name('mapping.view.data');

    // Upload routes
    Route::middleware('can:upload data')->group(function () {
        // Preview before upload
        Route::post('/upload/preview', [MappingController::class, 'showUploadPreview'])
            ->name('upload.preview');
        
        // Process upload
        Route::post('/upload/process', [MappingController::class, 'uploadData'])
            ->name('upload.process');

        // Cancel upload (sets cancel flag)
        Route::post('/upload/cancel', [MappingController::class, 'cancelUpload'])
            ->name('upload.cancel');
    });

    // Register format routes
    Route::middleware('can:register format')->group(function () {
        Route::get('/register-format', [MappingController::class, 'showRegisterForm'])
            ->name('mapping.register.form');
        Route::post('/register-format', [MappingController::class, 'processRegisterForm'])
            ->name('mapping.register.process');
        Route::post('/register-format/headers', [MappingController::class, 'extractHeaders'])
            ->name('mapping.register.headers');
        Route::delete('/mapping/{mapping}/data', [MappingController::class, 'clearData'])
            ->name('mapping.clear.data');
        Route::delete('/mapping/{mapping}', [MappingController::class, 'destroy'])
            ->name('mapping.destroy');
    });

    Route::get('/formats', [MappingController::class, 'index'])
        ->name('formats.index');
});

require __DIR__.'/auth.php';
