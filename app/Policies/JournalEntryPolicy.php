<?php

namespace App\Policies;

use App\Models\JournalEntry;
use App\Models\User;

class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.view');
    }

    public function view(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasPermission('accounting.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.manage');
    }
}
