<?php

namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\User;

class SalesOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sales.view');
    }

    public function view(User $user, SalesOrder $order): bool
    {
        return $user->hasPermission('sales.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sales.manage');
    }

    public function updateStatus(User $user, SalesOrder $order): bool
    {
        return $user->hasPermission('sales.manage');
    }

}
