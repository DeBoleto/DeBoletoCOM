<?php

namespace App\Modules\BoxOffice;

use App\Modules\BoxOffice\Policies\BoxOfficeAbilities;
use Illuminate\Support\ServiceProvider;

class BoxOfficeServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        BoxOfficeAbilities::register();

        $this->loadRoutesFrom(base_path('routes/modules/boxoffice.php'));
    }
}
