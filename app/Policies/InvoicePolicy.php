<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sales.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('sales.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sales.manage');
    }

    public function post(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('accounting.manage');
    }
}
