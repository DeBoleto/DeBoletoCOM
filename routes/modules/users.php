<?php

use App\Modules\Users\Http\Controllers\CustomerAuthController;
use App\Modules\Users\Http\Controllers\ProfileController;
use App\Modules\Users\Http\Middleware\EnsureCustomer;
use Illuminate\Support\Facades\Route;

// Sesión de clientes del sitio público (guard "customer").
Route::middleware(['web', 'throttle:30,1'])->controller(CustomerAuthController::class)->group(function () {
    Route::post('login/codigo', 'requestCode')->name('login.code');
    Route::post('login/verificar', 'verifyCode')->name('login.verify');
    Route::post('login/completar-registro', 'completeRegistration')->name('login.complete');
    Route::post('cliente/acceso', 'login')->name('customer.login');
    Route::post('cliente/registro', 'register')->name('customer.register');
    Route::post('cliente/salir', 'logout')->middleware('auth:customer')->name('customer.logout');
});

// Perfil del cliente (perfil/* de deboleto).
Route::middleware(['web', EnsureCustomer::class])->prefix('perfil')->name('profile.')->controller(ProfileController::class)->group(function () {
    Route::get('/', fn () => redirect()->route('profile.purchases'))->name('index');
    Route::get('mis-compras', 'purchases')->name('purchases');
    Route::get('boleto/digital/{codigo}', 'ticket')->name('ticket');
    Route::get('boleto/carrera/{codigo}', 'ticket')->name('race');
    Route::get('compras/{codigo}/boletos.pdf', 'pdf')->name('purchase.pdf');
    Route::get('mis-datos', 'account')->name('account');
    Route::put('mis-datos', 'updateAccount')->middleware('throttle:10,1')->name('account.update');
    Route::get('metodos-pago', 'cards')->name('cards');
    Route::delete('metodos-pago/{card}', 'deleteCard')->middleware('throttle:10,1')->name('cards.delete');
});

// Boleto digital por enlace (WhatsApp y correo); el código de la compra es la llave.
Route::middleware('web')->prefix('boleto/digital/{codigo}')->controller(ProfileController::class)->group(function () {
    Route::get('/', 'sharedTicket')->name('ticket.shared');
    Route::get('qr/{boleto}', 'qr')->whereNumber('boleto')->middleware('throttle:60,1')->name('ticket.qr');
    Route::get('estado', 'scans')->middleware('throttle:30,1')->name('ticket.scans');
    Route::get('wallet/apple', 'appleWallet')->middleware('throttle:10,1')->name('ticket.wallet.apple');
    Route::get('wallet/google', 'googleWallet')->middleware('throttle:10,1')->name('ticket.wallet.google');
});
