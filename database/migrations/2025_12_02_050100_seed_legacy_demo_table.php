<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv_legacy';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('legacy_demo_data')) {
            return;
        }

        $now = now();
        $rows = [
            [
                'record_code' => 'LEG-DEMO-001',
                'customer_name' => 'PT Demo Nusantara',
                'item_name' => 'Widget A',
                'quantity' => 10,
                'amount' => 150000.00,
                'transaction_date' => '2025-11-10',
                'division_code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'record_code' => 'LEG-DEMO-002',
                'customer_name' => 'CV Citra Abadi',
                'item_name' => 'Widget B',
                'quantity' => 5,
                'amount' => 85000.00,
                'transaction_date' => '2025-11-11',
                'division_code' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'record_code' => 'LEG-DEMO-003',
                'customer_name' => 'UD Maju Jaya',
                'item_name' => 'Widget C',
                'quantity' => 3,
                'amount' => 42000.00,
                'transaction_date' => '2025-11-12',
                'division_code' => 'DIV-LEG',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::connection($this->connection)->table('legacy_demo_data')->insert($rows);
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('legacy_demo_data')) {
            return;
        }

        DB::connection($this->connection)
            ->table('legacy_demo_data')
            ->whereIn('record_code', ['LEG-DEMO-001', 'LEG-DEMO-002', 'LEG-DEMO-003'])
            ->delete();
    }
};
