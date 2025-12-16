<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mapping_indices', function (Blueprint $table) {
            $table->string('upload_mode', 20)->nullable()->after('header_row')->index();
        });
    }

    public function down(): void
    {
        Schema::table('mapping_indices', function (Blueprint $table) {
            $table->dropColumn('upload_mode');
        });
    }
};
