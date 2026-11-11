<?php

use App\Modules\BoxOffice\Http\Controllers\AccessCountController;
use App\Modules\BoxOffice\Http\Controllers\AuthController;
use App\Modules\BoxOffice\Http\Controllers\CapacityController;
use App\Modules\BoxOffice\Http\Controllers\CheckoutController;
use App\Modules\BoxOffice\Http\Controllers\CourtesyCheckoutController;
use App\Modules\BoxOffice\Http\Controllers\CourtesyController;
use App\Modules\BoxOffice\Http\Controllers\DashboardController;
use App\Modules\BoxOffice\Http\Controllers\ExchangeController;
use App\Modules\BoxOffice\Http\Controllers\PurchaseController;
use App\Modules\BoxOffice\Http\Controllers\ReportsController;
use App\Modules\BoxOffice\Http\Controllers\SalesController;
use App\Modules\BoxOffice\Http\Controllers\StageInfoController;
use App\Modules\BoxOffice\Http\Controllers\TicketChangeController;
use App\Modules\BoxOffice\Http\Controllers\TicketEventsController;
use App\Modules\BoxOffice\Http\Middleware\EnsureMenuPrivilege;
use App\Modules\BoxOffice\Http\Middleware\ShareBoxOfficeData;
use App\Modules\BoxOffice\Policies\BoxOfficeAbilities;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', ShareBoxOfficeData::class])
    ->prefix('boxoffice')
    ->name('boxoffice.')
    ->group(function () {
        Route::get('/', fn () => redirect()->route(Auth::guard('boxoffice')->check() ? 'boxoffice.dashboard' : 'boxoffice.login'))
            ->name('home');

        Route::middleware('guest:boxoffice')->group(function () {
            Route::get('login', [AuthController::class, 'create'])->name('login');
            Route::post('login', [AuthController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
        });

        Route::middleware('auth:boxoffice')->group(function () {
            Route::post('logout', [AuthController::class, 'destroy'])->name('logout');

            Route::get('inicio', DashboardController::class)->name('dashboard');

            Route::middleware(EnsureMenuPrivilege::class.':tickets,boletos-venta')
                ->prefix('tickets')
                ->name('tickets.')
                ->group(function () {
                    Route::get('/', TicketEventsController::class)->name('index');

                    Route::prefix('checkout/{evento}')
                        ->whereNumber('evento')
                        ->name('checkout.')
                        ->controller(CheckoutController::class)
                        ->group(function () {
                            Route::get('/', 'start')->name('start');
                            Route::get('asientos', StageInfoController::class)->name('stage');
                            Route::get('seleccion', 'selection')->name('selection');
                            Route::post('seleccion', 'storeSelection')->name('selection.store');
                            Route::get('resumen', 'summary')->name('summary');
                            Route::post('confirmar', 'confirm')->name('confirm');
                            Route::get('venta-registrada', 'complete')->name('complete');
                            Route::post('finalizar', 'finish')->name('finish');
                            Route::post('cancelar', 'cancel')->name('cancel');
                        });

                    Route::prefix('compras/{compra}')
                        ->whereNumber('compra')
                        ->name('purchase.')
                        ->controller(PurchaseController::class)
                        ->group(function () {
                            Route::get('imprimir', 'print')->name('print');
                            Route::get('digital', 'digital')->name('digital');
                            Route::post('whatsapp', 'whatsapp')->middleware('throttle:10,1')->name('whatsapp');
                        });
                });

            Route::middleware(EnsureMenuPrivilege::class.':boletos-venta,tickets')->group(function () {
                Route::redirect('boletos-venta', '/boxoffice/tickets')->name('legacy-sales');
                Route::get('boletos-venta/checkout/{evento}', fn (int $evento) => redirect()->route('boxoffice.tickets.checkout.start', $evento))
                    ->whereNumber('evento');
            });

            Route::middleware([EnsureMenuPrivilege::class.':tickets-courtesy', 'can:'.BoxOfficeAbilities::PRIVILEGE.",'".config('boxoffice.privileges.courtesies')."'"])
                ->prefix('tickets-courtesy')
                ->name('courtesies.')
                ->controller(CourtesyController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('boletos/{boleto}/imprimir', 'print')->whereNumber('boleto')->name('print');
                    Route::delete('boletos/{boleto}', 'destroy')->whereNumber('boleto')->name('destroy');
                    Route::get('{evento}/imprimir-pendientes', 'printPending')->whereNumber('evento')->name('print-pending');
                });

            Route::middleware([EnsureMenuPrivilege::class.':tickets-courtesy-create,tickets-courtesy', 'can:'.BoxOfficeAbilities::PRIVILEGE.",'".config('boxoffice.privileges.courtesies')."'"])
                ->prefix('tickets-courtesy-create')
                ->name('courtesies.create.')
                ->controller(CourtesyCheckoutController::class)
                ->group(function () {
                    Route::get('/', 'events')->name('index');

                    Route::prefix('{evento}')->whereNumber('evento')->group(function () {
                        Route::get('/', 'selection')->name('selection');
                        Route::post('/', 'storeSelection')->name('selection.store');
                        Route::get('asientos', StageInfoController::class)->name('stage');
                        Route::get('confirmar', 'confirm')->name('confirm');
                        Route::post('confirmar', 'store')->name('store');
                        Route::get('listo', 'done')->name('done');
                        Route::get('imprimir', 'print')->name('print');
                        Route::post('finalizar', 'finish')->name('finish');
                    });
                });

            Route::middleware(EnsureMenuPrivilege::class.':tickets-exchange')
                ->prefix('tickets-exchange')
                ->name('exchange.')
                ->controller(ExchangeController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('compras/{compra}/imprimir', 'print')->whereNumber('compra')->name('print');
                    Route::get('compras/{compra}/digital', 'digital')->whereNumber('compra')->name('digital');
                });

            Route::middleware(EnsureMenuPrivilege::class.':tickets-count')
                ->prefix('tickets-count')
                ->name('count.')
                ->controller(AccessCountController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('escanear', 'scan')->middleware('throttle:240,1')->name('scan');
                });

            Route::middleware(EnsureMenuPrivilege::class.':tickets-change')
                ->prefix('tickets-change')
                ->name('change.')
                ->controller(TicketChangeController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('boleto', 'lookup')->name('lookup');
                    Route::get('eventos', 'events')->name('events');

                    Route::prefix('{evento}')->whereNumber('evento')->group(function () {
                        Route::get('/', 'selection')->name('selection');
                        Route::post('/', 'storeSelection')->name('selection.store');
                        Route::get('asientos', StageInfoController::class)->name('stage');
                        Route::get('resumen', 'summary')->name('summary');
                        Route::post('confirmar', 'confirm')->name('confirm');
                        Route::get('listo', 'done')->name('done');
                        Route::get('imprimir', 'print')->name('print');
                        Route::post('finalizar', 'finish')->name('finish');
                    });
                });

            Route::middleware(EnsureMenuPrivilege::class.':reportes,escenarios')->group(function () {
                Route::get('escenarios/capacity', [CapacityController::class, 'index'])->name('capacity.index');
                Route::get('escenarios/capacity/{evento}', [CapacityController::class, 'data'])->whereNumber('evento')->name('capacity.data');
                Route::redirect('reportes/capacity-in', '/boxoffice/escenarios/capacity');
                Route::redirect('reportes/localidad', '/boxoffice/escenarios/capacity');
            });

            Route::middleware(EnsureMenuPrivilege::class.':reportes')
                ->controller(ReportsController::class)
                ->group(function () {
                    Route::get('reportes', 'index')->name('reports.index');
                    Route::get('reportes/{tipo}/{evento}', 'show')
                        ->whereIn('tipo', ['general', 'pagos', 'whatsapp', 'detallado', 'ventas', 'estacionamiento', 'general-td', 'detallado-td'])
                        ->whereNumber('evento')
                        ->name('reports.show');
                });

            Route::middleware(EnsureMenuPrivilege::class.':reportes-por-dia,reportes')
                ->prefix('reportes-por-dia')
                ->name('reports.daily')
                ->controller(ReportsController::class)
                ->group(function () {
                    Route::get('/', 'daily');
                    Route::get('pdf', 'dailyPdf')->name('.pdf');
                });

            Route::middleware(EnsureMenuPrivilege::class.':reportes-contador-entradas,reportes')
                ->prefix('reportes-contador-entradas')
                ->name('reports.entries')
                ->controller(ReportsController::class)
                ->group(function () {
                    Route::get('/', 'entries');
                    Route::get('{evento}/funciones', 'entryFunctions')->whereNumber('evento')->name('.functions');
                    Route::get('{evento}/funciones/{funcion}', 'entryTotals')->whereNumber(['evento', 'funcion'])->name('.totals');
                });

            Route::middleware(EnsureMenuPrivilege::class.':reportes-por-categoria,reportes')
                ->prefix('reportes-por-categoria')
                ->name('reports.participants')
                ->controller(ReportsController::class)
                ->group(function () {
                    Route::get('/', 'participants');
                    Route::get('participantes', 'participantsExport')->name('.export');
                });

            Route::middleware(EnsureMenuPrivilege::class.':ventas')
                ->prefix('ventas')
                ->name('sales.')
                ->controller(SalesController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('enviar-boletos', 'mailForm')->name('mail');
                    Route::post('enviar-boletos', 'sendMail')->middleware('throttle:10,1')->name('mail.send');
                    Route::get('{evento}/corte-caja', 'cashCut')->whereNumber('evento')->name('cash-cut');
                    Route::get('{evento}/corte-caja/usuario/{usuario}', 'sellerCut')->whereNumber(['evento', 'usuario'])->name('seller-cut');
                    Route::get('{evento}/por-fecha', 'salesByDate')->whereNumber('evento')->name('by-date');
                });
        });
    });
