<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upload_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('division_id')->constrained()->onDelete('cascade');
            $table->foreignId('mapping_index_id')->constrained('mapping_indices')->onDelete('cascade');
            $table->string('file_name');
            $table->integer('rows_imported')->default(0);
            $table->string('status')->default('success');
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index(['created_at', 'division_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_logs');
    }
};