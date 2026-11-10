<?php

namespace App\Modules\Checkout;

use App\Modules\Checkout\Console\ReconcilePendingPurchases;
use App\Modules\Checkout\Events\WebPurchasePaid;
use App\Modules\Checkout\Listeners\DeliverPurchaseTickets;
use App\Modules\Checkout\Listeners\NotifyLigaMx;
use App\Modules\Checkout\Listeners\TrackPurchaseConversion;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CheckoutServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen(WebPurchasePaid::class, DeliverPurchaseTickets::class);
        Event::listen(WebPurchasePaid::class, TrackPurchaseConversion::class);
        Event::listen(WebPurchasePaid::class, NotifyLigaMx::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ReconcilePendingPurchases::class]);
        }

        $this->loadRoutesFrom(base_path('routes/modules/checkout.php'));
    }
}
