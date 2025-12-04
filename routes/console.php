<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\UploadLog;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Bersihkan upload_logs lebih dari 30 hari (jalan tiap hari jam 02:00)
Schedule::call(function () {
    UploadLog::where('created_at', '<', now()->subDays(30))
        ->orderBy('id')
        ->chunkById(500, function ($chunk) {
            UploadLog::whereIn('id', $chunk->pluck('id'))->delete();
        });
})->dailyAt('02:00')->name('cleanup-upload-logs-30d');
