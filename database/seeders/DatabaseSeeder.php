<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ------------------------------------------------------------------
        // Roles
        // ------------------------------------------------------------------
        $admin = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Full access to every ERP module and user management.',
        ]);

        $manager = Role::create([
            'name' => 'Manager',
            'slug' => 'manager',
            'description' => 'Can operate sales, purchasing, inventory and accounting.',
        ]);

        $staff = Role::create([
            'name' => 'Staff',
            'slug' => 'staff',
            'description' => 'Read-only access to ERP modules and daily operations.',
        ]);

        // ------------------------------------------------------------------
        // Permissions
        // ------------------------------------------------------------------
        $allPermissions = [
            ['name' => 'View Dashboard', 'slug' => 'dashboard.view'],
            ['name' => 'View Inventory', 'slug' => 'inventory.view'],
            ['name' => 'Manage Inventory', 'slug' => 'inventory.manage'],
            ['name' => 'View Sales', 'slug' => 'sales.view'],
            ['name' => 'Manage Sales', 'slug' => 'sales.manage'],
            ['name' => 'View Purchasing', 'slug' => 'purchasing.view'],
            ['name' => 'Manage Purchasing', 'slug' => 'purchasing.manage'],
            ['name' => 'View Accounting', 'slug' => 'accounting.view'],
            ['name' => 'Manage Accounting', 'slug' => 'accounting.manage'],
            ['name' => 'View Reports', 'slug' => 'reports.view'],
            ['name' => 'Export Reports', 'slug' => 'reports.export'],
            ['name' => 'Manage Users', 'slug' => 'users.manage'],
            ['name' => 'Manage Roles', 'slug' => 'roles.manage'],
        ];

        $permissions = collect($allPermissions)->map(fn (array $p) => Permission::create($p));

        $managerPermissionSlugs = [
            'dashboard.view',
            'inventory.view',
            'inventory.manage',
            'sales.view',
            'sales.manage',
            'purchasing.view',
            'purchasing.manage',
            'accounting.view',
            'accounting.manage',
            'reports.view',
            'reports.export',
        ];

        $staffPermissionSlugs = [
            'dashboard.view',
            'inventory.view',
            'sales.view',
            'purchasing.view',
            'accounting.view',
            'reports.view',
        ];

        $admin->permissions()->sync($permissions->pluck('id'));
        $manager->permissions()->sync(
            $permissions->whereIn('slug', $managerPermissionSlugs)->pluck('id')
        );
        $staff->permissions()->sync(
            $permissions->whereIn('slug', $staffPermissionSlugs)->pluck('id')
        );

        // ------------------------------------------------------------------
        // Demo users
        // ------------------------------------------------------------------
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@erp.test',
            'password' => Hash::make('password'),
        ]);
        $adminUser->assignRole('admin');

        $managerUser = User::create([
            'name' => 'Manager User',
            'email' => 'manager@erp.test',
            'password' => Hash::make('password'),
        ]);
        $managerUser->assignRole('manager');

        $staffUser = User::create([
            'name' => 'Staff User',
            'email' => 'staff@erp.test',
            'password' => Hash::make('password'),
        ]);
        $staffUser->assignRole('staff');
    }
}
