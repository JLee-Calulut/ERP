<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchasing.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission('purchasing.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchasing.manage');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission('purchasing.manage');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission('purchasing.manage');
    }
}
