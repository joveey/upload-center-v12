<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mapping_upload_runs', 'inactivated_at')) {
            Schema::table('mapping_upload_runs', function (Blueprint $table) {
                $table->timestamp('inactivated_at')->nullable()->after('activated_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mapping_upload_runs', 'inactivated_at')) {
            Schema::table('mapping_upload_runs', function (Blueprint $table) {
                $table->dropColumn('inactivated_at');
            });
        }
    }
};
