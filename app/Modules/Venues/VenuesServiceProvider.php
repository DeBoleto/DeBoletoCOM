<?php

namespace App\Modules\Venues;

use Illuminate\Support\ServiceProvider;

class VenuesServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/modules/venues.php'));
    }
}
