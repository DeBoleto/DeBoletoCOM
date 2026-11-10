<?php

namespace App\Modules\Payments;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\OpenPay\OpenPayGateway;
use Illuminate\Support\ServiceProvider;

class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, OpenPayGateway::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/modules/payments.php'));
    }
}
