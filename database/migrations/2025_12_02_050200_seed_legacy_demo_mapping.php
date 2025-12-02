<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mapping_indices') || ! Schema::hasTable('mapping_columns')) {
            return;
        }

        // Ambil divisi pertama sebagai fallback
        $divisionId = DB::table('divisions')->value('id');

        if (! $divisionId) {
            return;
        }

        $mapping = DB::table('mapping_indices')->where('code', 'legacy_demo')->first();

        if (! $mapping) {
            $id = DB::table('mapping_indices')->insertGetId([
                'division_id' => $divisionId,
                'code' => 'legacy_demo',
                'description' => 'Legacy Demo Data',
                'table_name' => 'legacy_demo_data',
                'header_row' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $id = $mapping->id;
            DB::table('mapping_indices')->where('id', $id)->update([
                'table_name' => 'legacy_demo_data',
                'updated_at' => now(),
            ]);
        }

        $columns = [
            ['excel_column_index' => 'A', 'table_column_name' => 'record_code'],
            ['excel_column_index' => 'B', 'table_column_name' => 'customer_name'],
            ['excel_column_index' => 'C', 'table_column_name' => 'item_name'],
            ['excel_column_index' => 'D', 'table_column_name' => 'quantity'],
            ['excel_column_index' => 'E', 'table_column_name' => 'amount'],
            ['excel_column_index' => 'F', 'table_column_name' => 'transaction_date'],
            ['excel_column_index' => 'G', 'table_column_name' => 'division_code'],
        ];

        foreach ($columns as $col) {
            DB::table('mapping_columns')->updateOrInsert(
                [
                    'mapping_index_id' => $id,
                    'excel_column_index' => $col['excel_column_index'],
                ],
                [
                    'table_column_name' => $col['table_column_name'],
                    'data_type' => 'string',
                    'is_required' => false,
                    'is_unique_key' => false,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        $mapping = DB::table('mapping_indices')->where('code', 'legacy_demo')->first();
        if ($mapping) {
            DB::table('mapping_columns')->where('mapping_index_id', $mapping->id)->delete();
            DB::table('mapping_indices')->where('id', $mapping->id)->delete();
        }
    }
};
