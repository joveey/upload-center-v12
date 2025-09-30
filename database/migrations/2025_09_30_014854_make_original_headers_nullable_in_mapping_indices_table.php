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
            // Mengubah kolom agar bisa menerima nilai NULL
            $table->json('original_headers')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mapping_indices', function (Blueprint $table) {
            // Mengembalikan seperti semula jika migrasi di-rollback
            $table->json('original_headers')->nullable(false)->change();
        });
    }
};