<?php

use App\Http\Controllers\Portal\AccessHistoryController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\LgpdTermController;
use App\Http\Controllers\Portal\ProcurationController;
use App\Http\Controllers\Portal\RepresentationController;
use App\Http\Middleware\ResolveRepresentation;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        // Rotas do próprio termo fora do gate lgpd.accepted (evita loop de redirect).
        Route::get('termo-lgpd', [LgpdTermController::class, 'show'])->name('termo-lgpd.show');
        Route::post('termo-lgpd', [LgpdTermController::class, 'accept'])->name('termo-lgpd.accept');

        Route::middleware(['lgpd.accepted', ResolveRepresentation::class])->group(function () {
            Route::get('painel', DashboardController::class)->name('dashboard');

            Route::get('acessos', AccessHistoryController::class)->name('acessos.index');

            Route::get('procuracoes', [ProcurationController::class, 'index'])->name('procuracoes.index');
            Route::post('procuracoes', [ProcurationController::class, 'store'])->name('procuracoes.store');
            Route::delete('procuracoes/{procuration}', [ProcurationController::class, 'destroy'])->name('procuracoes.destroy');

            Route::post('representacao', [RepresentationController::class, 'store'])->name('representacao.store');
            Route::delete('representacao', [RepresentationController::class, 'destroy'])->name('representacao.destroy');
        });
    });
