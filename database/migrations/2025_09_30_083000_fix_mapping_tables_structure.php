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
        // Fix mapping_indices table
        Schema::table('mapping_indices', function (Blueprint $table) {
            // Cek dan ubah struktur kolom
            if (Schema::hasColumn('mapping_indices', 'name')) {
                // Rename name -> code
                DB::statement('ALTER TABLE mapping_indices RENAME COLUMN name TO code');
            }
            
            // Tambah kolom description sebagai nullable dulu
            if (!Schema::hasColumn('mapping_indices', 'description')) {
                $table->string('description')->nullable()->after('code');
            }
            
            // Hapus kolom yang tidak diperlukan
            if (Schema::hasColumn('mapping_indices', 'original_headers')) {
                $table->dropColumn('original_headers');
            }
            if (Schema::hasColumn('mapping_indices', 'destination_table')) {
                $table->dropColumn('destination_table');
            }
        });

        // Isi description dengan nilai dari code untuk data existing
        DB::statement("UPDATE mapping_indices SET description = code WHERE description IS NULL");

        // Ubah description menjadi NOT NULL setelah diisi
        Schema::table('mapping_indices', function (Blueprint $table) {
            DB::statement('ALTER TABLE mapping_indices ALTER COLUMN description SET NOT NULL');
        });

        // Fix mapping_columns table
        Schema::table('mapping_columns', function (Blueprint $table) {
            // Rename kolom jika diperlukan
            if (Schema::hasColumn('mapping_columns', 'excel_column')) {
                DB::statement('ALTER TABLE mapping_columns RENAME COLUMN excel_column TO excel_column_index');
            }
            
            if (Schema::hasColumn('mapping_columns', 'database_column')) {
                DB::statement('ALTER TABLE mapping_columns RENAME COLUMN database_column TO table_column_name');
            }
            
            // Tambah kolom baru jika belum ada
            if (!Schema::hasColumn('mapping_columns', 'data_type')) {
                $table->string('data_type')->default('string')->after('table_column_name');
            }
            
            if (!Schema::hasColumn('mapping_columns', 'is_required')) {
                $table->boolean('is_required')->default(false)->after('data_type');
            }
        });

        // Update unique constraint untuk code
        Schema::table('mapping_indices', function (Blueprint $table) {
            // Drop old unique constraint jika ada
            $table->unique('code');
        });

        // Update unique constraint untuk excel_column_index
        Schema::table('mapping_columns', function (Blueprint $table) {
            $table->unique(['mapping_index_id', 'excel_column_index'], 'mapping_columns_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mapping_indices', function (Blueprint $table) {
            if (Schema::hasColumn('mapping_indices', 'code')) {
                DB::statement('ALTER TABLE mapping_indices RENAME COLUMN code TO name');
            }
            
            if (Schema::hasColumn('mapping_indices', 'description')) {
                $table->dropColumn('description');
            }
        });

        Schema::table('mapping_columns', function (Blueprint $table) {
            if (Schema::hasColumn('mapping_columns', 'excel_column_index')) {
                DB::statement('ALTER TABLE mapping_columns RENAME COLUMN excel_column_index TO excel_column');
            }
            
            if (Schema::hasColumn('mapping_columns', 'table_column_name')) {
                DB::statement('ALTER TABLE mapping_columns RENAME COLUMN table_column_name TO database_column');
            }
            
            if (Schema::hasColumn('mapping_columns', 'data_type')) {
                $table->dropColumn('data_type');
            }
            
            if (Schema::hasColumn('mapping_columns', 'is_required')) {
                $table->dropColumn('is_required');
            }
        });
    }
};