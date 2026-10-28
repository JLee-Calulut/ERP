<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

class DashboardService
{
    /**
     * KPI figures. On Day 2 only the tables that exist can be counted
     * (users, roles, permissions). Revenue, orders, low stock, customers,
     * open POs and net income are added as their modules arrive on
     * Days 3-7.
     */
    public function stats(): array
    {
        return [
            'users' => User::count(),
            'roles' => Role::count(),
            'permissions' => Permission::count(),
            'administrators' => User::whereHas('roles', fn ($q) => $q->where('slug', 'admin'))
                ->when(auth()->id(), fn ($query, $id) => $query->whereKeyNot($id))
                ->count(),
        ];
    }

    /**
     * Shortcuts rendered on the dashboard; only what exists on Day 2.
     */
    public function quickActions(): array
    {
        $user = auth()->user();
        $canManageUsers = $user?->hasPermission('users.manage') ?? false;
        $canManageRoles = $user?->hasPermission('roles.manage') ?? false;

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
        ];
    }
}
