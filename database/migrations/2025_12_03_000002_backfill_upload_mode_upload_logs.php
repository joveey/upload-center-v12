<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Set upload_mode to 'upsert' for past upload logs that are still null
        DB::table('upload_logs')
            ->where('action', 'upload')
            ->whereNull('upload_mode')
            ->update(['upload_mode' => 'upsert']);
    }

    public function down(): void
    {
        // Revert only the rows we touched (action=upload and mode=upsert with no error_message to keep scope narrow)
        DB::table('upload_logs')
            ->where('action', 'upload')
            ->where('upload_mode', 'upsert')
            ->update(['upload_mode' => null]);
    }
};
