<?php

use App\Http\Controllers\Gestao\AccessHistoryController;
use App\Http\Controllers\Gestao\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:acessar-gestao', 'lgpd.accepted'])
    ->prefix('gestao')
    ->name('gestao.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('acessos/{user}', AccessHistoryController::class)
            ->middleware('permission:consultar-acessos-de-qualquer-conta')
            ->name('acessos.show');
    });
