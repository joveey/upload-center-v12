<?php
// routes/web.php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LegacyFormatController;
use App\Http\Controllers\MappingController;
use App\Http\Controllers\UploadRunController;
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
    
    // Export routes
    Route::middleware('can:export data')->group(function () {
        Route::get('/export/{mapping}', [App\Http\Controllers\ExportController::class, 'export'])
            ->name('export.data');
    });
    Route::middleware('can:download template')->group(function () {
        Route::get('/export-template/{mapping}', [App\Http\Controllers\ExportController::class, 'exportTemplate'])
            ->name('export.template');
    });
    
    // View data route - NEW
    Route::middleware('can:view data')->group(function () {
        Route::get('/mapping/{mapping}/view', [MappingController::class, 'viewData'])
            ->name('mapping.view.data');
    });

    // Upload routes
    Route::middleware('can:upload data')->group(function () {
        // Preview before upload
        Route::post('/upload/preview', [MappingController::class, 'showUploadPreview'])
            ->name('upload.preview');
        
        // Process upload
        Route::post('/upload/process', [MappingController::class, 'queueUpload'])
            ->name('upload.process');

        // Strict mode upload (delete & replace by period)
        Route::post('/upload/strict', [MappingController::class, 'uploadDataStrict'])
            ->name('upload.strict');

        // Cancel upload (sets cancel flag)
        Route::post('/upload/cancel', [MappingController::class, 'cancelUpload'])
            ->name('upload.cancel');

        // Trim whitespace on existing data
        Route::post('/mapping/{mapping}/clean', [MappingController::class, 'cleanData'])
            ->name('mapping.clean.data');
    });

    // Register format routes (create/update)
    Route::middleware('can:create format')->group(function () {
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

    Route::post('/legacy-format/quick-map', [LegacyFormatController::class, 'quickMap'])
        ->middleware('can:create format')
        ->name('legacy.format.quick-map');

    Route::get('/legacy-format/{mapping}', [LegacyFormatController::class, 'index'])
        ->name('legacy.format.index');

    // Upload runs polling
    Route::get('/uploads/recent', [UploadRunController::class, 'recent'])
        ->name('uploads.recent');
    Route::delete('/uploads/recent', [UploadRunController::class, 'clear'])
        ->name('uploads.clear');

    // Activity log
    Route::get('/activity', [UploadLogController::class, 'index'])
        ->name('logs.index');

    // User management (superuser only)
    Route::middleware('role:superuser')->group(function () {
        Route::get('/admin/users', [UserManagementController::class, 'index'])
            ->name('admin.users.index');
        Route::get('/admin/users/list', [UserManagementController::class, 'list'])
            ->name('admin.users.list');
        Route::post('/admin/users', [UserManagementController::class, 'store'])
            ->name('admin.users.store');
        Route::put('/admin/users/{user}', [UserManagementController::class, 'update'])
            ->name('admin.users.update');
        Route::delete('/admin/users/{user}', [UserManagementController::class, 'destroy'])
            ->name('admin.users.destroy');

        // Division management
        Route::resource('divisions', \App\Http\Controllers\DivisionController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    // Delete / clear format
    Route::middleware('can:delete format')->group(function () {
        Route::delete('/mapping/{mapping}/data', [MappingController::class, 'clearData'])
            ->name('mapping.clear.data');
        Route::delete('/mapping/{mapping}', [MappingController::class, 'destroy'])
            ->name('mapping.destroy');
    });
});

require __DIR__.'/auth.php';
