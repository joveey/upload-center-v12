<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapping_upload_runs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('mapping_index_id');
            $table->unsignedInteger('upload_index');
            $table->date('period_date')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('inactivated_at')->nullable();
            $table->timestamp('dropped_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['mapping_index_id', 'status']);
            $table->index(['mapping_index_id', 'period_date']);
            $table->unique(['mapping_index_id', 'period_date', 'upload_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapping_upload_runs');
    }
};
