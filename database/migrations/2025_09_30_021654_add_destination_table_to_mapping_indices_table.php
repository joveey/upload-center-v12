<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mapping_indices', function (Blueprint $table) {
            // Kolom untuk menyimpan nama tabel tujuan
            $table->string('destination_table')->after('name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mapping_indices', function (Blueprint $table) {
            $table->dropColumn('destination_table');
        });
    }
};