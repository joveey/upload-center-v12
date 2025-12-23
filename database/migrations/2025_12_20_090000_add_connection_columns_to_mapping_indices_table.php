<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasConnection = Schema::hasColumn('mapping_indices', 'connection');
        $hasTargetConnection = Schema::hasColumn('mapping_indices', 'target_connection');

        if ($hasConnection && $hasTargetConnection) {
            return;
        }

        Schema::table('mapping_indices', function (Blueprint $table) use ($hasConnection, $hasTargetConnection) {
            if (! $hasConnection) {
                $table->string('connection')->nullable();
            }
            if (! $hasTargetConnection) {
                $table->string('target_connection')->nullable();
            }
        });
    }

    public function down(): void
    {
        $hasConnection = Schema::hasColumn('mapping_indices', 'connection');
        $hasTargetConnection = Schema::hasColumn('mapping_indices', 'target_connection');

        if (! $hasConnection && ! $hasTargetConnection) {
            return;
        }

        Schema::table('mapping_indices', function (Blueprint $table) use ($hasConnection, $hasTargetConnection) {
            if ($hasTargetConnection) {
                $table->dropColumn('target_connection');
            }
            if ($hasConnection) {
                $table->dropColumn('connection');
            }
        });
    }
};
