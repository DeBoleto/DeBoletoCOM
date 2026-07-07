<?php

namespace App\Modules\Tickets;

use Illuminate\Support\ServiceProvider;

class TicketsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/modules/tickets.php'));
    }
}
