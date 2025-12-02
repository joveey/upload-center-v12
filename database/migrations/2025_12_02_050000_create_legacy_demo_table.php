<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Force this table to be created in the legacy connection.
     */
    protected $connection = 'sqlsrv_legacy';

    public function up(): void
    {
        // Jangan buat jika sudah ada.
        if (Schema::connection($this->connection)->hasTable('legacy_demo_data')) {
            return;
        }

        Schema::connection($this->connection)->create('legacy_demo_data', function (Blueprint $table) {
            $table->id();
            $table->string('record_code', 50)->nullable()->index();
            $table->string('customer_name', 200)->nullable()->index();
            $table->string('item_name', 150)->nullable();
            $table->integer('quantity')->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->date('transaction_date')->nullable()->index();
            $table->string('division_code', 50)->nullable(); // untuk filter unassigned
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('legacy_demo_data');
    }
};
