<?php

use App\Modules\Users\Http\Controllers\CustomerAuthController;
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
