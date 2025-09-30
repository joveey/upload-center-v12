<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mapping_indices', function (Blueprint $table) {
            // Menambahkan kolom untuk menyimpan nama tabel dinamis
            $table->string('table_name')->after('name')->nullable();
            
            // Menambahkan kolom untuk menyimpan baris header/awal data
            $table->integer('header_row')->after('table_name')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mapping_indices', function (Blueprint $table) {
            $table->dropColumn('table_name');
            $table->dropColumn('header_row');
        });
    }
};