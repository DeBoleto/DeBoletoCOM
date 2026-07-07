<?php

namespace App\Modules\Orders;

use Illuminate\Support\ServiceProvider;

class OrdersServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/modules/orders.php'));
    }
}
