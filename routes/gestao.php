<?php

use App\Http\Controllers\Gestao\AccessHistoryController;
use App\Http\Controllers\Gestao\CnaeController;
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

        // Consulta granular separada da manutenção (HU-011 CA-04)
        Route::middleware('permission:consultar-cnaes')->group(function () {
            Route::get('cnaes', [CnaeController::class, 'index'])->name('cnaes.index');
        });

        Route::middleware('permission:manter-cnaes')->group(function () {
            Route::post('cnaes', [CnaeController::class, 'store'])->name('cnaes.store');
            Route::put('cnaes/{cnae}', [CnaeController::class, 'update'])->name('cnaes.update');
            Route::delete('cnaes/{cnae}', [CnaeController::class, 'destroy'])->name('cnaes.destroy');
        });
    });
