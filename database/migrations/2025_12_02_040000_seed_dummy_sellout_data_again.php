<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Force this seeder-style migration to run on the legacy connection.
     */
    protected $connection = 'sqlsrv_legacy';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('dummy_sellout_data')) {
            return;
        }

        $gids = ['GID-LEG-001', 'GID-LEG-002', 'GID-LEG-003'];
        DB::connection($this->connection)
            ->table('dummy_sellout_data')
            ->whereIn('gid', $gids)
            ->delete();

        $now = now();
        $rows = [
            [
                'gid' => 'GID-LEG-001',
                'area' => 'West',
                'branch' => 'Jakarta',
                'transaction_date' => '2025-11-01',
                'model' => 'ABC-123',
                'customer_name' => 'PT Example Nusantara',
                'division_code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'gid' => 'GID-LEG-002',
                'area' => 'East',
                'branch' => 'Surabaya',
                'transaction_date' => '2025-11-02',
                'model' => 'XYZ-789',
                'customer_name' => 'CV Demo Sejahtera',
                'division_code' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'gid' => 'GID-LEG-003',
                'area' => 'Central',
                'branch' => 'Bandung',
                'transaction_date' => '2025-11-03',
                'model' => 'LMN-456',
                'customer_name' => 'UD Sukses Makmur',
                'division_code' => 'DIV-OLD',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::connection($this->connection)
            ->table('dummy_sellout_data')
            ->insert($rows);
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('dummy_sellout_data')) {
            return;
        }

        DB::connection($this->connection)
            ->table('dummy_sellout_data')
            ->whereIn('gid', ['GID-LEG-001', 'GID-LEG-002', 'GID-LEG-003'])
            ->delete();
    }
};
