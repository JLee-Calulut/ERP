<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.view');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->hasPermission('inventory.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.manage');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->hasPermission('inventory.manage');
    }

    public function delete(User $user, Category $category): bool
    {
        if (! $user->hasPermission('inventory.manage')) {
            return false;
        }

        // Categories still in use by products cannot be deleted.
        return $category->products()->count() === 0;
    }
}
