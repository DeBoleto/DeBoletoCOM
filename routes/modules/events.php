<?php

use App\Http\Controllers\Api\CategoriesController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/api/search', [SearchController::class, 'search'])->name('api.search');

Route::get('/api/categories', [CategoriesController::class, 'index'])->name('api.categories');

Route::get('/buscar', [CategoriesController::class, 'show'])->name('buscar');
