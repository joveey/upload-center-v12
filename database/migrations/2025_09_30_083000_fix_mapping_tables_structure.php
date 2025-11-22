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
        Schema::table('mapping_indices', function (Blueprint $table) {
            if (Schema::hasColumn('mapping_indices', 'name') && ! Schema::hasColumn('mapping_indices', 'code')) {
                $table->renameColumn('name', 'code');
            }

            if (! Schema::hasColumn('mapping_indices', 'description')) {
                $table->string('description')->nullable()->after('code');
            }

            if (Schema::hasColumn('mapping_indices', 'original_headers')) {
                $table->dropColumn('original_headers');
            }

            if (Schema::hasColumn('mapping_indices', 'destination_table')) {
                $table->dropColumn('destination_table');
            }
        });

        DB::table('mapping_indices')
            ->whereNull('description')
            ->update(['description' => DB::raw('code')]);

        Schema::table('mapping_indices', function (Blueprint $table) {
            if (Schema::hasColumn('mapping_indices', 'description')) {
                $table->string('description')->nullable(false)->change();
            }
        });

        Schema::table('mapping_columns', function (Blueprint $table) {
            if (Schema::hasColumn('mapping_columns', 'excel_column')) {
                $table->renameColumn('excel_column', 'excel_column_index');
            }

            if (Schema::hasColumn('mapping_columns', 'database_column')) {
                $table->renameColumn('database_column', 'table_column_name');
            }

            if (! Schema::hasColumn('mapping_columns', 'data_type')) {
                $table->string('data_type')->default('string')->after('table_column_name');
            }

            if (! Schema::hasColumn('mapping_columns', 'is_required')) {
                $table->boolean('is_required')->default(false)->after('data_type');
            }
        });

        Schema::table('mapping_indices', function (Blueprint $table) {
            $table->unique('code');
        });

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
            if (Schema::hasColumn('mapping_indices', 'code') && ! Schema::hasColumn('mapping_indices', 'name')) {
                $table->renameColumn('code', 'name');
            }

            if (Schema::hasColumn('mapping_indices', 'description')) {
                $table->dropColumn('description');
            }
        });

        Schema::table('mapping_columns', function (Blueprint $table) {
            if (Schema::hasColumn('mapping_columns', 'excel_column_index')) {
                $table->dropUnique('mapping_columns_unique');
                $table->renameColumn('excel_column_index', 'excel_column');
            }

            if (Schema::hasColumn('mapping_columns', 'table_column_name')) {
                $table->renameColumn('table_column_name', 'database_column');
            }

            if (Schema::hasColumn('mapping_columns', 'data_type')) {
                $table->dropColumn('data_type');
            }

            if (Schema::hasColumn('mapping_columns', 'is_required')) {
                $table->dropColumn('is_required');
            }
        });

        Schema::table('mapping_columns', function (Blueprint $table) {
            $table->unique(['mapping_index_id', 'excel_column']);
        });
    }
};
