<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel; // <-- Tambahkan ini
use PHPUnit\Framework\Attributes\Test;
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
}