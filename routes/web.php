<?php

use App\Http\Controllers\MappingController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /*
    |--------------------------------------------------------------------------
    | Mapping Routes
    |--------------------------------------------------------------------------
    | Rute untuk fitur inti Upload Center Cerdas.
    | Dilindungi oleh permission 'register format'.
    |
    */
    Route::middleware('can:register format')->group(function () {
        Route::get('/register-format', [MappingController::class, 'showRegisterForm'])->name('mapping.register.form');
        Route::post('/register-format', [MappingController::class, 'processRegisterForm'])->name('mapping.register.process');
        Route::get('/register-format/map', [MappingController::class, 'showMapForm'])->name('mapping.map.form');
        Route::post('/register-format/map', [MappingController::class, 'storeMapping'])->name('mapping.map.store');
    });

});

require __DIR__.'/auth.php';