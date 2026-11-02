<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Product;

class DashboardService
{
    public function stats(): array
    {
        return [
            'users' => User::count(),
            'roles' => Role::count(),
            'permissions' => Permission::count(),
            'administrators' => User::whereHas('roles', fn ($q) => $q->where('slug', 'admin'))
                ->when(auth()->id(), fn ($query, $id) => $query->whereKeyNot($id))
                ->count(),
            'lowStock' => Product::whereColumn('stock_quantity', '<=', 'low_stock_threshold')->count(),
            'products' => Product::count(),
        ];
    }

    public function quickActions(): array
    {
        $user = auth()->user();
        $canManageUsers = $user?->hasPermission('users.manage') ?? false;
        $canManageRoles = $user?->hasPermission('roles.manage') ?? false;
        $canManageInventory = $user?->hasPermission('inventory.manage') ?? false;

        return [
            [
                'label' => 'Manage users',
                'icon' => '🪪',
                'href' => $canManageUsers ? '/erp/users' : null,
                'available' => $canManageUsers,
            ],
            [
                'label' => 'Roles & permissions',
                'icon' => '🔐',
                'href' => $canManageRoles ? '/erp/roles' : null,
                'available' => $canManageRoles,
            ],
            [
                'label' => 'Receive stock',
                'icon' => '📥',
                'href' => $canManageInventory ? '/erp/inventory/stock/create' : null,
                'available' => $canManageInventory,
            ],
            [
                'label' => 'Manage products',
                'icon' => '📦',
                'href' => $canManageInventory ? '/erp/inventory/products' : null,
                'available' => $canManageInventory,
            ],
        ];
    }
}
