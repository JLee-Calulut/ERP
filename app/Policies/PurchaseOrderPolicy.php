<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchasing.view');
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasPermission('purchasing.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchasing.manage');
    }

    public function updateStatus(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasPermission('purchasing.manage');
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasPermission('purchasing.manage');
    }
}
