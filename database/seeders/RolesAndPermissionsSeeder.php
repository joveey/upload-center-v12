<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // create permissions
        Permission::create(['name' => 'register format']);
        Permission::create(['name' => 'upload data']);

        // create roles and assign existing permissions
        $divisionUserRole = Role::create(['name' => 'division-user']);
        $divisionUserRole->givePermissionTo('register format');
        $divisionUserRole->givePermissionTo('upload data');

        // Super-Admin gets all permissions implicitly by Gate::before rule
        // see AuthServiceProvider
        Role::create(['name' => 'super-admin']);
    }
}