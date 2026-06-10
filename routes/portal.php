<?php

use App\Http\Controllers\Portal\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
    });
