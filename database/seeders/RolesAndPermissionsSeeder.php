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
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = config('auth.defaults.guard', 'web');

        $registerFormat = Permission::firstOrCreate([
            'name' => 'register format',
            'guard_name' => $guard,
        ]);

        $uploadData = Permission::firstOrCreate([
            'name' => 'upload data',
            'guard_name' => $guard,
        ]);

        $legacyView = Permission::firstOrCreate([
            'name' => 'legacy.format.view',
            'guard_name' => $guard,
        ]);

        $divisionUserRole = Role::firstOrCreate([
            'name' => 'division-user',
            'guard_name' => $guard,
        ]);
        $divisionUserRole->givePermissionTo([$registerFormat, $uploadData]);

        $superAdminRole = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => $guard,
        ]);
        $superAdminRole->givePermissionTo(Permission::all());
    }
}
