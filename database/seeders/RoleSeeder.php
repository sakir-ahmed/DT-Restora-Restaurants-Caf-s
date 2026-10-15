<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Permissions grouped by module. super-admin bypasses checks entirely
     * (Gate::before); every other role gets an explicit list.
     */
    private const PERMISSIONS = [
        'admin.access',
        'branches.view',
        'branches.manage',
        'menu.view',
        'menu.manage',
        'staff.view',
        'staff.manage',
        'orders.view',
        'orders.create',
        'orders.void',
        'discounts.override',
        'inventory.view',
        'inventory.manage',
        'stock.adjust',
        'purchases.manage',
        'recipes.manage',
        'wastage.log',
        'wastage.approve',
        'transfers.manage',
        'employees.manage',
        'shifts.manage',
        'pos.access',
        'kds.access',
        'payments.manage',
        'reports.view',
        'dashboard.view',
        'accounting.view',
        'journals.post',
        'vouchers.manage',
        'periods.lock',
        'customers.view',
        'customers.manage',
        'loyalty.manage',
        'coupons.manage',
        'delivery.dispatch',
        'riders.manage',
        'aggregators.manage',
        'settings.manage',
        'activity.view',
        'sync.resolve',
    ];

    private const ROLE_PERMISSIONS = [
        'owner' => '*',
        'branch-manager' => [
            'admin.access', 'branches.view', 'menu.view', 'menu.manage', 'staff.view',
            'orders.view', 'orders.create', 'orders.void', 'discounts.override',
            'inventory.view', 'inventory.manage', 'stock.adjust', 'purchases.manage',
            'recipes.manage', 'wastage.log', 'wastage.approve', 'transfers.manage',
            'employees.manage', 'shifts.manage', 'payments.manage',
            'pos.access', 'kds.access',
            'reports.view', 'dashboard.view', 'customers.view', 'customers.manage',
            'loyalty.manage', 'delivery.dispatch', 'riders.manage', 'sync.resolve',
        ],
        'cashier' => [
            'admin.access', 'branches.view', 'menu.view', 'orders.view', 'orders.create',
            'pos.access', 'shifts.manage', 'customers.view', 'loyalty.manage',
        ],
        'waiter' => [
            'admin.access', 'branches.view', 'menu.view', 'orders.view', 'orders.create',
        ],
        'kitchen-manager' => [
            'admin.access', 'branches.view', 'menu.view', 'orders.view', 'kds.access',
            'inventory.view', 'inventory.manage', 'stock.adjust', 'purchases.manage',
            'recipes.manage', 'wastage.log',
        ],
        'accountant' => [
            'admin.access', 'branches.view', 'reports.view', 'dashboard.view',
            'accounting.view', 'journals.post', 'vouchers.manage', 'periods.lock',
        ],
        'rider' => [
            'admin.access', 'branches.view',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name);
        }

        foreach (['super-admin', 'owner', 'branch-manager', 'cashier', 'waiter', 'kitchen-manager', 'accountant', 'rider'] as $role) {
            Role::findOrCreate($role);
        }

        foreach (self::ROLE_PERMISSIONS as $role => $permissions) {
            $roleModel = Role::findOrCreate($role);

            $names = $permissions === '*' ? self::PERMISSIONS : $permissions;

            $roleModel->syncPermissions($names);
        }

        // super-admin passes every Gate check via Gate::before; give it the
        // full list too so permission listings show it.
        Role::findOrCreate('super-admin')->syncPermissions(self::PERMISSIONS);
    }
}
