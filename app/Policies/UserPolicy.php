<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function delete(User $user, User $target): bool
    {
        if (! $user->hasPermission('users.manage')) {
            return false;
        }

        // An admin cannot delete their own account.
        if ($user->is($target)) {
            return false;
        }

        return true;
    }
}
