<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upload_logs', function (Blueprint $table) {
            $table->string('action')->default('upload')->after('status');
            $table->string('upload_mode')->nullable()->after('action');
        });
    }

    public function down(): void
    {
        Schema::table('upload_logs', function (Blueprint $table) {
            $table->dropColumn(['upload_mode', 'action']);
        });
    }
};
