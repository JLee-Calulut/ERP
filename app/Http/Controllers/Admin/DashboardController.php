<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(DashboardService $service): Response
    {
        $user = auth()->user();

        return Inertia::render('Erp/Dashboard', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->all(),
            ],
            'stats' => $service->stats(),
            'quickActions' => $service->quickActions(),
        ]);
    }
}
