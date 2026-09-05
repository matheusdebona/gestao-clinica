<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'roles.manage',
            'permissions.view',
            'files.upload',
            'files.delete',
            'clinics.view',
            'clinics.manage',
            'product_types.manage',
            'brands.manage',
            'units.manage',
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'products.adjust_stock',
            'protocols.view',
            'protocols.create',
            'protocols.update',
            'protocols.delete',
            'clients.view',
            'clients.create',
            'clients.update',
            'clients.delete',
            'payment_methods.manage',
            'card_operators.manage',
            'card_brands.manage',
            'card_fees.manage',
            'sales.view',
            'sales.create',
            'sales.update',
            'sales.confirm',
            'sales.cancel',
            'budgets.view',
            'budgets.create',
            'budgets.update',
            'budgets.convert',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $superAdmin->syncPermissions(Permission::all());

        $admin = Role::findOrCreate('admin', 'web');
        $admin->syncPermissions([
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'roles.manage',
            'permissions.view',
            'files.upload',
            'files.delete',
            'clinics.view',
            'product_types.manage',
            'brands.manage',
            'units.manage',
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'products.adjust_stock',
            'protocols.view',
            'protocols.create',
            'protocols.update',
            'protocols.delete',
            'clients.view',
            'clients.create',
            'clients.update',
            'clients.delete',
            'payment_methods.manage',
            'card_operators.manage',
            'card_brands.manage',
            'card_fees.manage',
            'sales.view',
            'sales.create',
            'sales.update',
            'sales.confirm',
            'sales.cancel',
            'budgets.view',
            'budgets.create',
            'budgets.update',
            'budgets.convert',
        ]);
    }
}
