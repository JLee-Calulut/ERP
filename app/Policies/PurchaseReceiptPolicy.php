<?php

namespace App\Policies;

use App\Models\PurchaseReceipt;
use App\Models\User;

class PurchaseReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchasing.view');
    }

    public function view(User $user, PurchaseReceipt $purchaseReceipt): bool
    {
        return $user->hasPermission('purchasing.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchasing.manage');
    }

}
