<?php
// routes/web.php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LegacyFormatController;
use App\Http\Controllers\MappingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UploadLogController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

// Public Welcome Page (accessible by everyone)
Route::get('/', function () {
    return view('auth.login');
})->middleware('guest')->name('Login Page');

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

    // Register format routes (create/update) - still allowed for permitted users
    Route::middleware('can:register format')->group(function () {
        Route::get('/register-format', [MappingController::class, 'showRegisterForm'])
            ->name('mapping.register.form');
        Route::post('/register-format', [MappingController::class, 'processRegisterForm'])
            ->name('mapping.register.process');
        Route::post('/register-format/headers', [MappingController::class, 'extractHeaders'])
            ->name('mapping.register.headers');
    });

    Route::get('/formats', [MappingController::class, 'index'])
        ->name('formats.index');

    Route::get('/legacy-format', [LegacyFormatController::class, 'list'])
        ->name('legacy.format.list');

    Route::get('/legacy-format/{mapping}', [LegacyFormatController::class, 'index'])
        ->name('legacy.format.index');

    // Activity log
    Route::get('/activity', [UploadLogController::class, 'index'])
        ->name('logs.index');

    // User management (super admin only)
    Route::middleware('role:super-admin')->group(function () {
        Route::get('/admin/users', [UserManagementController::class, 'index'])
            ->name('admin.users.index');
        Route::post('/admin/users', [UserManagementController::class, 'store'])
            ->name('admin.users.store');
        // Only super admin can delete format or clear data
        Route::delete('/mapping/{mapping}/data', [MappingController::class, 'clearData'])
            ->name('mapping.clear.data');
        Route::delete('/mapping/{mapping}', [MappingController::class, 'destroy'])
            ->name('mapping.destroy');
    });
});

require __DIR__.'/auth.php';
