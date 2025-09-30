<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // create permissions
        Permission::create(['name' => 'register format']);
        Permission::create(['name' => 'upload data']);

        // create roles and assign existing permissions
        $divisionUserRole = Role::create(['name' => 'division-user']);
        $divisionUserRole->givePermissionTo('register format');
        $divisionUserRole->givePermissionTo('upload data');

        // [PERBAIKAN] Menghapus 's' dari 'name's'
        $superAdminRole = Role::create(['name' => 'super-admin']);
        $superAdminRole->givePermissionTo(Permission::all());
    }
}