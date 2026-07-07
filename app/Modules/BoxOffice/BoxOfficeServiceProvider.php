<?php

namespace App\Modules\BoxOffice;

use Illuminate\Support\ServiceProvider;

class BoxOfficeServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/modules/boxoffice.php'));
    }
}
