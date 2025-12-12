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

        // Create permissions
        $permissions = [
            'manage users',
            'create format',
            'delete format',
            'upload data',
            'download template',
            'view data',
            'export data',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => $guard,
            ]);
        }

        // Roles
        $superuser = Role::firstOrCreate(['name' => 'superuser', 'guard_name' => $guard]);
        $superuser->syncPermissions(Permission::all());

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard]);
        $admin->syncPermissions([
            'create format',
            'delete format',
            'upload data',
            'view data',
            'download template',
            'export data',
        ]);

        $user = Role::firstOrCreate(['name' => 'user', 'guard_name' => $guard]);
        $user->syncPermissions([
            'upload data',
            'download template',
        ]);

        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => $guard]);
        $viewer->syncPermissions([
            'view data',
            'export data',
        ]);
    }
}
