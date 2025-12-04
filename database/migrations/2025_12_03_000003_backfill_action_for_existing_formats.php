<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tidak ada log create_format historis; tidak ada backfill otomatis yang aman.
        // Hanya memastikan kolom action tidak null untuk baris upload.
        DB::table('upload_logs')
            ->whereNull('action')
            ->update(['action' => 'upload']);
    }

    public function down(): void
    {
        DB::table('upload_logs')
            ->where('action', 'upload')
            ->update(['action' => null]);
    }
};
