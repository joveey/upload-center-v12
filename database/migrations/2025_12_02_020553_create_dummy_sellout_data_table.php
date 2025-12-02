<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Force this migration to run on the legacy connection.
     */
    protected $connection = 'sqlsrv_legacy';

    public function up(): void
    {
        // Create only if it does not yet exist on the legacy DB.
        if (Schema::connection($this->connection)->hasTable('dummy_sellout_data')) {
            return;
        }

        Schema::connection($this->connection)->create('dummy_sellout_data', function (Blueprint $table) {
            $table->id(); // identity / autoincrement

            // Kolom dummy standar buat test legacy listing
            $table->string('gid', 50)->nullable()->index();
            $table->string('area', 100)->nullable();
            $table->string('branch', 100)->nullable();
            $table->date('transaction_date')->nullable()->index();
            $table->string('model', 100)->nullable()->index();
            $table->string('customer_name', 200)->nullable()->index();

            // Optional: buat konsep legacy division
            $table->string('division_code', 50)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('dummy_sellout_data');
    }
};
