<?php

use App\Http\Controllers\Api\VenuesController;
use Illuminate\Support\Facades\Route;

// Rutas del módulo venues

Route::get('/api/venues', [VenuesController::class, 'index'])->name('api.venues');

Route::get('/recinto/{slug}', [VenuesController::class, 'show'])->name('venue.detail');
