<?php

namespace App\Modules\Users;

use Illuminate\Support\ServiceProvider;

class UsersServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/modules/users.php'));
    }
}
