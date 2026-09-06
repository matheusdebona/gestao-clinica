<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class EnsureRolesAndPermissions
{
    public const GUARD = 'web';

    /** @var list<string> */
    public const PROTECTED_ROLES = ['admin', 'super-admin'];

    /** @var list<string> */
    public const ASSIGNABLE_ROLES = ['receptionist', 'seller', 'stock', 'professional'];

    /** @var list<string> */
    public const PERMISSIONS = [
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
        'clinics.branding',
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
        'client_origins.manage',
        'campaigns.manage',
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
        'documents.view',
        'documents.generate',
        'documents.delete',
        'treatments.view',
        'treatments.manage',
        'treatments.start',
        'treatments.complete',
        'treatments.cancel',
        'metrics.view',
    ];

    public static function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, self::GUARD);
        }

        $superAdmin = Role::findOrCreate('super-admin', self::GUARD);
        $superAdmin->syncPermissions(Permission::all());

        $admin = Role::findOrCreate('admin', self::GUARD);
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
            'clinics.branding',
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
            'client_origins.manage',
            'campaigns.manage',
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
            'documents.view',
            'documents.generate',
            'documents.delete',
            'treatments.view',
            'treatments.manage',
            'treatments.start',
            'treatments.complete',
            'treatments.cancel',
            'metrics.view',
        ]);

        Role::findOrCreate('receptionist', self::GUARD)->syncPermissions([
            'clients.view',
            'clients.create',
            'clients.update',
            'clients.delete',
            'budgets.view',
            'budgets.create',
            'budgets.update',
            'budgets.convert',
            'sales.view',
            'sales.create',
            'documents.view',
        ]);

        Role::findOrCreate('seller', self::GUARD)->syncPermissions([
            'clients.view',
            'clients.create',
            'clients.update',
            'products.view',
            'protocols.view',
            'sales.view',
            'sales.create',
            'sales.update',
            'sales.confirm',
            'sales.cancel',
            'budgets.view',
            'budgets.create',
            'budgets.update',
            'budgets.convert',
            'documents.view',
            'documents.generate',
            'treatments.view',
            'treatments.start',
            'payment_methods.manage',
            'card_operators.manage',
            'card_brands.manage',
            'card_fees.manage',
        ]);

        Role::findOrCreate('stock', self::GUARD)->syncPermissions([
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'products.adjust_stock',
            'product_types.manage',
            'brands.manage',
            'units.manage',
            'files.upload',
        ]);

        Role::findOrCreate('professional', self::GUARD)->syncPermissions([
            'clients.view',
            'treatments.view',
            'treatments.manage',
            'treatments.start',
            'treatments.complete',
            'treatments.cancel',
            'documents.view',
            'documents.generate',
            'documents.delete',
            'products.view',
        ]);
    }

    public static function isAssignable(string $role): bool
    {
        return in_array($role, self::ASSIGNABLE_ROLES, true);
    }

    public static function isProtected(string $role): bool
    {
        return in_array($role, self::PROTECTED_ROLES, true);
    }
}
