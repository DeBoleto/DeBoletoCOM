<?php

use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/api/search', [SearchController::class, 'search'])->name('api.search');
