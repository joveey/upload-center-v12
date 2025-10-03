<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Dapatkan semua tabel yang terdaftar di mapping_indices
        $mappingIndices = DB::table('mapping_indices')->get();
        
        foreach ($mappingIndices as $mapping) {
            $tableName = $mapping->table_name;
            
            // Cek apakah tabel ada dan belum punya kolom period_date
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'period_date')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->date('period_date')->nullable()->index()->after('id');
                });
                
                // Set default period_date untuk data yang sudah ada
                DB::table($tableName)
                    ->whereNull('period_date')
                    ->update(['period_date' => now()->format('Y-m-d')]);
                
                echo "Added period_date column to table: {$tableName}\n";
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $mappingIndices = DB::table('mapping_indices')->get();
        
        foreach ($mappingIndices as $mapping) {
            $tableName = $mapping->table_name;
            
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'period_date')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('period_date');
                });
            }
        }
    }
};