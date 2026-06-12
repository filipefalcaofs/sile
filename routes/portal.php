<?php

use App\Http\Controllers\Portal\AccessHistoryController;
use App\Http\Controllers\Portal\CnpjLookupController;
use App\Http\Controllers\Portal\CompanyController;
use App\Http\Controllers\Portal\CompanyLinkController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\GovBrLoginController;
use App\Http\Controllers\Portal\LgpdTermController;
use App\Http\Controllers\Portal\ProcurationController;
use App\Http\Controllers\Portal\RepresentationController;
use App\Http\Middleware\ResolveRepresentation;
use Illuminate\Support\Facades\Route;

// Login Único GOV.BR (HU-151) — rotas públicas de guest, fora do Fortify.
Route::middleware('guest')
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('login/govbr', [GovBrLoginController::class, 'redirect'])->name('govbr.redirect');
        Route::get('login/govbr/callback', [GovBrLoginController::class, 'callback'])->name('govbr.callback');
    });

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

            // Empresas: rotas literais ANTES de empresas/{company} (03-05).
            Route::get('empresas', [CompanyController::class, 'index'])->name('empresas.index');
            Route::get('empresas/cadastrar', [CompanyController::class, 'create'])->name('empresas.create');
            Route::post('empresas', [CompanyController::class, 'store'])->name('empresas.store');
            Route::post('empresas/consultar-cnpj', CnpjLookupController::class)->name('empresas.consultar-cnpj');

            // Rotas com binding {company} DEPOIS das literais (03-05).
            Route::get('empresas/{company}', [CompanyController::class, 'show'])->name('empresas.show');
            Route::put('empresas/{company}', [CompanyController::class, 'update'])->name('empresas.update');
            Route::delete('empresas/{company}/vinculo', [CompanyLinkController::class, 'destroy'])->name('empresas.vinculo.destroy');
        });
    });
