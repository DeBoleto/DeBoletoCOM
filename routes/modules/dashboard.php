<?php

use App\Modules\BoxOffice\Http\Controllers\CourtesyCheckoutController;
use App\Modules\BoxOffice\Http\Controllers\ReportsController;
use App\Modules\BoxOffice\Http\Controllers\StageInfoController;
use App\Modules\BoxOffice\Http\Middleware\EnsureMenuPrivilege;
use App\Modules\BoxOffice\Http\Middleware\ShareBoxOfficeData;
use App\Modules\Dashboard\Enums\StaffType;
use App\Modules\Dashboard\Http\Controllers\AuthController;
use App\Modules\Dashboard\Http\Controllers\EventsController;
use App\Modules\Dashboard\Http\Controllers\HomeController;
use App\Modules\Dashboard\Http\Controllers\PurchasesController;
use App\Modules\Dashboard\Http\Controllers\RefundsController;
use App\Modules\Dashboard\Http\Controllers\StaffController;
use App\Modules\Dashboard\Http\Controllers\TicketsController;
use App\Modules\Dashboard\Http\Controllers\UserAccessController;
use App\Modules\Dashboard\Http\Controllers\UserDocumentsController;
use App\Modules\Dashboard\Http\Controllers\VenuesController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Administración del back-office (grupo legacy /dashboard). /dashboard en este
// proyecto es la cuenta de clientes de Jetstream, por eso vive en /admin.
Route::middleware(['web', ShareBoxOfficeData::class.':admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', fn () => redirect()->route(Auth::guard('admin')->check() ? 'admin.home' : 'admin.login'))->name('root');

        Route::middleware('guest:admin')->group(function () {
            Route::get('login', [AuthController::class, 'create'])->name('login');
            Route::post('login', [AuthController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
        });

        Route::middleware('auth:admin')->group(function () {
            Route::post('logout', [AuthController::class, 'destroy'])->name('logout');
            Route::get('inicio', HomeController::class)->name('home');

            Route::middleware(EnsureMenuPrivilege::class.':eventos')->group(function () {
                Route::controller(EventsController::class)->prefix('eventos')->name('events.')->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('nuevo', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::get('formatos/{escenario}', 'formats')->whereNumber('escenario')->name('formats');
                    Route::get('{evento}', 'show')->whereNumber('evento')->name('show');
                    Route::patch('{evento}/banderas', 'updateFlag')->whereNumber('evento')->name('flags');
                    Route::put('{evento}/localidades/{localidad}', 'updateLocality')->whereNumber(['evento', 'localidad'])->name('localities.update');
                    Route::get('{evento}/compradores', 'buyers')->whereNumber('evento')->name('buyers');
                });

                Route::controller(TicketsController::class)->prefix('eventos/{evento}/boletos')->whereNumber('evento')->name('tickets.')->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('excel', 'export')->name('export');
                    Route::delete('{boleto}', 'destroy')->whereNumber('boleto')->name('destroy');
                    Route::post('{boleto}/desbloquear', 'unlock')->whereNumber('boleto')->name('unlock');
                });
                Route::post('eventos/{evento}/bloqueos-olmecas', [TicketsController::class, 'olmecas'])->whereNumber('evento')->name('events.olmecas');
                Route::get('eventos/boletos-por-fecha', [TicketsController::class, 'exportByDate'])->name('tickets.by-date');

                Route::get('eventos/{evento}/reembolsos', [RefundsController::class, 'index'])->whereNumber('evento')->name('refunds.index');
                Route::get('eventos/{evento}/reembolsos/excel', [RefundsController::class, 'export'])->whereNumber('evento')->name('refunds.export');
                Route::get('eventos/defender', [RefundsController::class, 'defenderEvents'])->name('defender.index');
                Route::get('eventos/defender/reportes/{tipo}/{evento}', [ReportsController::class, 'show'])
                    ->whereIn('tipo', ['general-td', 'detallado-td', 'general', 'detallado'])
                    ->whereNumber('evento')
                    ->name('defender.report');
                Route::get('eventos-excel/participantes', [ReportsController::class, 'participantsExport'])->name('events.participants');

                Route::prefix('eventos/cortesias')->name('events.courtesies.')->controller(CourtesyCheckoutController::class)->group(function () {
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
            });

            Route::middleware(EnsureMenuPrivilege::class.':tickets-unlock,eventos')
                ->get('tickets-unlock', fn () => redirect()->route('admin.events.index')->with('success', 'Abre un evento y entra a "Boletos" para desbloquear su impresión.'))
                ->name('tickets.unlock');

            Route::middleware(EnsureMenuPrivilege::class.':actualizar,eventos')->prefix('actualizar')->name('purchases.')->group(function () {
                Route::get('/', [PurchasesController::class, 'index'])->name('index');
                Route::post('{compra}/efectivo', [PurchasesController::class, 'convertToCash'])->whereNumber('compra')->name('cash');
                Route::post('{compra}/impresion', [TicketsController::class, 'enablePrinting'])->whereNumber('compra')->name('reprint');
                Route::get('{compra}/digital', [TicketsController::class, 'digital'])->whereNumber('compra')->name('digital');
            });

            Route::middleware(EnsureMenuPrivilege::class.':escenarios')
                ->prefix('escenarios')
                ->name('venues.')
                ->controller(VenuesController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('nuevo', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::post('vista-previa', 'preview')->name('preview');
                    Route::get('{escenario}/editar', 'edit')->whereNumber('escenario')->name('edit');
                    Route::post('{escenario}', 'update')->whereNumber('escenario')->name('update');
                    Route::get('{escenario}/formatos', 'formats')->whereNumber('escenario')->name('formats');
                    Route::post('{escenario}/formatos', 'storeFormat')->whereNumber('escenario')->name('formats.store');
                    Route::put('{escenario}/formatos/{formato}', 'updateFormat')->whereNumber(['escenario', 'formato'])->name('formats.update');
                });

            Route::middleware(EnsureMenuPrivilege::class.':administradores')
                ->prefix('administradores/{usuario}/documentos')
                ->whereNumber('usuario')
                ->name('documents.')
                ->controller(UserDocumentsController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('{documento}', 'download')->whereNumber('documento')->name('download');
                    Route::delete('{documento}', 'destroy')->whereNumber('documento')->name('destroy');
                });

            Route::middleware(EnsureMenuPrivilege::class)
                ->prefix('{tipo}')
                ->whereIn('tipo', StaffType::sections())
                ->name('staff.')
                ->group(function () {
                    Route::controller(StaffController::class)->group(function () {
                        Route::get('/', 'index')->name('index');
                        Route::get('nuevo', 'create')->name('create');
                        Route::post('/', 'store')->name('store');
                        Route::get('{usuario}/editar', 'edit')->whereNumber('usuario')->name('edit');
                        Route::put('{usuario}', 'update')->whereNumber('usuario')->name('update');
                        Route::delete('{usuario}', 'destroy')->whereNumber('usuario')->name('destroy');
                        Route::post('{usuario}/reactivar', 'restore')->whereNumber('usuario')->name('restore');
                    });

                    Route::prefix('{usuario}')->whereNumber('usuario')->controller(UserAccessController::class)->group(function () {
                        Route::get('eventos', 'events')->name('events');
                        Route::post('eventos', 'assignEvent')->name('events.store');
                        Route::delete('eventos/{evento}', 'removeEvent')->whereNumber('evento')->name('events.destroy');
                        Route::get('privilegios', 'privileges')->name('privileges');
                        Route::put('privilegios', 'syncPrivileges')->name('privileges.update');
                    });
                });
        });
    });

// Enlaces del sistema anterior: /dashboard/<sección> → /admin/<sección>.
Route::middleware('web')
    ->get('dashboard/{path}', fn (string $path) => redirect('/admin/'.$path, 301))
    ->where('path', '.+');
