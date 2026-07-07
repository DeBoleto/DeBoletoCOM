<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    App\Providers\JetstreamServiceProvider::class,

    // ── Módulos ──────────────────────────────────────────
    App\Modules\Events\EventsServiceProvider::class,
    App\Modules\Tickets\TicketsServiceProvider::class,
    App\Modules\Orders\OrdersServiceProvider::class,
    App\Modules\Venues\VenuesServiceProvider::class,
    App\Modules\Users\UsersServiceProvider::class,
    App\Modules\BoxOffice\BoxOfficeServiceProvider::class,
    App\Modules\Payments\PaymentsServiceProvider::class,
    App\Modules\Notifications\NotificationsServiceProvider::class,
    App\Modules\Reports\ReportsServiceProvider::class,
];
