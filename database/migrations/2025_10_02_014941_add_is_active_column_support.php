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
        // Get all tables from mapping_indices
        $mappingIndices = DB::table('mapping_indices')->get();
        
        foreach ($mappingIndices as $mapping) {
            $tableName = $mapping->table_name;
            
            // Check if table exists and doesn't have is_active column
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'is_active')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->boolean('is_active')->default(true)->index()->after('period_date');
                });
                
                // Set all existing data as active
                DB::table($tableName)->update(['is_active' => true]);
                
                echo "Added is_active column to table: {$tableName}\n";
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
            
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'is_active')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('is_active');
                });
            }
        }
    }
};
