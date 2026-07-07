<?php

namespace App\Modules\Events;

use Illuminate\Support\ServiceProvider;

class EventsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/modules/events.php'));
    }
}
