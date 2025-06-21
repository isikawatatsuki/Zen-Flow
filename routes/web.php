<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ApiDataController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/fetchData', [ApiDataController::class, 'fetchData']);


Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
