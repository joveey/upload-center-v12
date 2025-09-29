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
        Schema::create('mapping_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mapping_index_id')->constrained('mapping_indices')->cascadeOnDelete();
            $table->string('excel_column');
            $table->string('database_column');
            $table->timestamps();

            // A single mapping rule cannot map the same Excel column twice.
            $table->unique(['mapping_index_id', 'excel_column']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mapping_columns');
    }
};