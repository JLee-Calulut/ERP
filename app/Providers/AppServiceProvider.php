<?php

namespace App\Providers;

use App\Models\ReportSnapshot;
use App\Policies\ReportPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The reports resource is the ReportSnapshot model (there is no
        // standalone "Report" model), so the Report policy is bound to it
        // here instead of relying on the model-name policy discovery.
        Gate::policy(ReportSnapshot::class, ReportPolicy::class);
    }
}
