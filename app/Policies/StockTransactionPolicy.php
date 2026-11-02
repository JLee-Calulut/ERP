<?php

namespace App\Policies;

use App\Models\User;

class StockTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.manage');
    }
}
