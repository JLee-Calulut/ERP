<?php

namespace App\Policies;

use App\Models\ReportSnapshot;
use App\Models\User;

class ReportPolicy
{
    /**
     * The reports module is read-plus-run: anyone with reports.view can
     * browse the library, open a completed snapshot and trigger a new
     * generation. There is no separate manage permission — running a report
     * writes its snapshot/export, not any source data.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('reports.view');
    }

    public function view(User $user, ReportSnapshot $reportSnapshot): bool
    {
        return $user->hasPermission('reports.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('reports.view');
    }

    /**
     * Downloading an export is a privileged action beyond viewing: only
     * users holding the dedicated reports.export permission (managers and
     * admins) may stream a snapshot's CSV. The route group already guards
     * reports.view, so this gate is purely the export right.
     */
    public function export(User $user, ReportSnapshot $reportSnapshot): bool
    {
        return $user->hasPermission('reports.export');
    }
}
