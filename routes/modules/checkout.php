<?php

use App\Modules\Checkout\Http\Controllers\OpenPayWebhookController;
use App\Modules\Checkout\Http\Controllers\OrderController;
use App\Modules\Checkout\Http\Controllers\PaymentController;
use App\Modules\Checkout\Http\Controllers\SeatsController;
use Illuminate\Support\Facades\Route;

// Compra web. El paso de función vive en el detalle del evento, así que el
// flujo empieza en el mapa: asientos → pago → confirmación.
Route::middleware('web')->group(function () {
    Route::get('evento/{slug}/asientos', [SeatsController::class, 'show'])->name('event.seats');

    Route::prefix('compra')->name('checkout.')->group(function () {
        Route::get('confirmacion', [PaymentController::class, 'return'])->name('return');

        Route::whereNumber('evento')->group(function () {
            Route::get('{evento}/funciones/{funcion}/secciones/{seccion}', [SeatsController::class, 'section'])
                ->whereNumber('funcion')
                ->middleware('throttle:120,1')
                ->name('section');
            Route::post('{evento}/asientos', [SeatsController::class, 'hold'])->middleware('throttle:90,1')->name('hold');
            Route::post('{evento}/seleccion', [SeatsController::class, 'store'])->middleware('throttle:30,1')->name('selection');
            Route::post('{evento}/cancelar', [SeatsController::class, 'cancel'])->name('cancel');

            Route::get('{evento}/pago', [PaymentController::class, 'show'])->name('payment');
            Route::post('{evento}/cotizacion', [PaymentController::class, 'quote'])->middleware('throttle:30,1')->name('quote');
            Route::post('{evento}/pago', [PaymentController::class, 'pay'])->middleware('throttle:10,1')->name('pay');
        });

        Route::whereUuid('codigo')->group(function () {
            Route::get('{codigo}', [OrderController::class, 'show'])->name('complete');
            Route::get('{codigo}/boletos.pdf', [OrderController::class, 'tickets'])->name('tickets');
        });
    });
});

// Sin sesión ni CSRF: OpenPay se autentica con Basic Auth.
Route::post('webhooks/openpay', OpenPayWebhookController::class)
    ->middleware('throttle:240,1')
    ->name('webhooks.openpay');
