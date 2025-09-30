<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->boolean('is_super_user')->default(false)->after('name');
        });

        // Update atau buat divisi SuperUser
        DB::table('divisions')->updateOrInsert(
            ['name' => 'SuperUser'],
            ['is_super_user' => true, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->dropColumn('is_super_user');
        });
    }
};