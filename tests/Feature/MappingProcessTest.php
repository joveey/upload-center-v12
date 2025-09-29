<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\MappingIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MappingProcessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** @test */
    public function user_can_register_a_new_format()
    {
        // Buat divisi dan user
        $division = Division::factory()->create(['name' => 'Test Division']);
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'division_id' => $division->id
        ]);

        // Assign role/permission jika menggunakan spatie permission
        // $user->givePermissionTo('register format');

        // Login sebagai user
        $this->actingAs($user);

        // Buat file Excel palsu
        $file = UploadedFile::fake()->create('test.xlsx', 100);

        // Kirim request ke endpoint register format
        $response = $this->post(route('mapping.register.process'), [
            'name' => 'Test Format',
            'excel_file' => $file,
            'header_row' => 1,
        ]);

        // Assert redirect ke halaman map form
        $response->assertRedirect(route('mapping.map.form'));

        // Assert session memiliki data mapping
        $this->assertNotNull(session('mapping_data'));
    }

    /** @test */
    public function user_can_complete_mapping_process()
    {
        // Buat divisi dan user
        $division = Division::factory()->create(['name' => 'Test Division']);
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'division_id' => $division->id
        ]);

        // Login sebagai user
        $this->actingAs($user);

        // Set session data
        session([
            'mapping_data' => [
                'name' => 'Test Format',
                'header_row' => 1,
                'file_path' => 'temp/test.xlsx',
                'excel_headers' => ['Name', 'Email', 'Phone'],
                'destination_table' => 'spotify_users',
            ]
        ]);

        // Kirim request mapping
        $response = $this->post(route('mapping.map.store'), [
            'mappings' => [
                'Name' => 'name',
                'Email' => 'email',
                'Phone' => 'phone',
            ],
        ]);

        // Assert redirect ke dashboard
        $response->assertRedirect(route('dashboard'));

        // Assert mapping tersimpan di database
        $this->assertDatabaseHas('mapping_indices', [
            'name' => 'Test Format',
            'division_id' => $division->id,
        ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_mapping_routes()
    {
        // Test register form
        $response = $this->get(route('mapping.register.form'));
        $response->assertRedirect(route('login'));

        // Test map form
        $response = $this->get(route('mapping.map.form'));
        $response->assertRedirect(route('login'));
    }
}