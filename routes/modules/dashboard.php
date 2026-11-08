<?php

use App\Modules\BoxOffice\Http\Middleware\EnsureMenuPrivilege;
use App\Modules\BoxOffice\Http\Middleware\ShareBoxOfficeData;
use App\Modules\Dashboard\Enums\StaffType;
use App\Modules\Dashboard\Http\Controllers\AuthController;
use App\Modules\Dashboard\Http\Controllers\HomeController;
use App\Modules\Dashboard\Http\Controllers\StaffController;
use App\Modules\Dashboard\Http\Controllers\UserAccessController;
use App\Modules\Dashboard\Http\Controllers\UserDocumentsController;
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
