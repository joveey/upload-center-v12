<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('upload_runs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('mapping_index_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('file_name')->nullable();
            $table->string('stored_xlsx_path');
            $table->string('sheet_name')->nullable();
            $table->string('upload_mode', 20)->nullable();
            $table->date('period_date')->nullable();
            $table->text('selected_columns')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('progress_percent')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['mapping_index_id', 'period_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_runs');
    }
};
