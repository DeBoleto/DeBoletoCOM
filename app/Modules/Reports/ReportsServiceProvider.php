<?php

namespace App\Modules\Reports;

use Illuminate\Support\ServiceProvider;

class ReportsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/modules/reports.php'));
    }
}
