<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\MappingColumn;
use App\Models\MappingIndex;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel; // <-- Tambahkan ini
use PHPUnit\Framework\Attributes\Test;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MappingProcessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_register_a_new_mapping_format(): void
    {
        // 1. Persiapan
        Storage::fake('local');
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $division = Division::factory()->create(['name' => 'Finance']);
        $user = User::factory()->create(['division_id' => $division->id]);
        $user->assignRole('division-user');

        // 2. Simulasi Langkah 1: Buat dan unggah file XLSX asli
        $fileName = 'financial_report.xlsx';
        $headings = ['user_id', 'nama_pengguna', 'alamat_email', 'jumlah_follower', 'country'];
        
        // Gunakan Maatwebsite\Excel untuk membuat file XLSX yang valid di storage palsu
        Excel::store(new class($headings) {
            public function __construct(private array $headings) {}
            public function array(): array { return [$this->headings]; }
        }, $fileName, 'local');

        // Buat objek UploadedFile dari file XLSX yang baru saja kita buat
        $fakeExcelFile = new UploadedFile(
            Storage::disk('local')->path($fileName),
            $fileName,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true // Menandakan ini adalah file tes
        );
        
        $formatName = 'Laporan Keuangan Q3';
        $headerRow = 1;

        $response = $this->actingAs($user)
            ->post(route('mapping.register.process'), [
                'name' => $formatName,
                'excel_file' => $fakeExcelFile,
                'header_row' => $headerRow,
            ]);

        // 3. Assert (Periksa) hasil langkah 1
        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('mapping_data');

        $sessionData = session('mapping_data');

        // 4. Simulasi Langkah 2: Kirim data pemetaan
        $mappingPayload = [
            'mappings' => [
                'user_id' => 'user_id',
                'nama_pengguna' => 'display_name',
                'alamat_email' => 'email',
                'jumlah_follower' => 'followers',
            ],
        ];

        $response = $this->actingAs($user)
            ->withSession(['mapping_data' => $sessionData])
            ->post(route('mapping.map.store'), $mappingPayload);

        // 5. Assert (Periksa) hasil akhir
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('mapping_indices', [
            'division_id' => $division->id,
            'name' => $formatName,
        ]);

        $this->assertDatabaseHas('mapping_columns', [
            'excel_column' => 'nama_pengguna',
            'database_column' => 'display_name',
        ]);

        $this->assertDatabaseMissing('mapping_columns', [
            'excel_column' => 'country',
        ]);
    }

    #[Test]
    public function user_can_upload_strict_mode_replacing_old_period_data(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $division = Division::factory()->create();
        $user = User::factory()->create(['division_id' => $division->id]);
        $user->givePermissionTo('upload data');

        $tableName = 'tmp_strict_uploads';
        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('customer');
            $table->integer('amount');
            $table->date('period_date');
            $table->timestamps();
        });

        DB::table($tableName)->insert([
            [
                'customer' => 'OldCo',
                'amount' => 100,
                'period_date' => '2025-06-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'customer' => 'KeepCo',
                'amount' => 300,
                'period_date' => '2025-07-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $mapping = MappingIndex::create([
            'division_id' => $division->id,
            'code' => 'tmp_strict',
            'description' => 'Temporary Strict Table',
            'table_name' => $tableName,
            'header_row' => 1,
        ]);

        MappingColumn::create([
            'mapping_index_id' => $mapping->id,
            'excel_column_index' => 'A',
            'table_column_name' => 'customer',
            'data_type' => 'string',
            'is_required' => false,
            'is_unique_key' => false,
        ]);

        MappingColumn::create([
            'mapping_index_id' => $mapping->id,
            'excel_column_index' => 'B',
            'table_column_name' => 'amount',
            'data_type' => 'string',
            'is_required' => false,
            'is_unique_key' => false,
        ]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['customer', 'amount'],
            ['NewCo', 200],
        ], null, 'A1');

        $tempPath = storage_path('app/strict_upload_test.xlsx');
        (new Xlsx($spreadsheet))->save($tempPath);

        $uploadedFile = new UploadedFile(
            $tempPath,
            'strict_upload_test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($user)->post(route('upload.strict'), [
            'data_file' => $uploadedFile,
            'mapping_id' => $mapping->id,
            'period_date' => '2025-06-01',
        ]);

        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseMissing($tableName, [
            'customer' => 'OldCo',
            'period_date' => '2025-06-01',
        ]);

        $this->assertDatabaseHas($tableName, [
            'customer' => 'NewCo',
            'period_date' => '2025-06-01',
            'amount' => 200,
        ]);

        $this->assertDatabaseHas($tableName, [
            'customer' => 'KeepCo',
            'period_date' => '2025-07-01',
        ]);
    }
}
