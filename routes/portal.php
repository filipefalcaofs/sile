<?php

use App\Http\Controllers\Portal\AccessHistoryController;
use App\Http\Controllers\Portal\CnaeSearchController;
use App\Http\Controllers\Portal\CnpjLookupController;
use App\Http\Controllers\Portal\CompanyCnaeController;
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

// Termo LGPD é compartilhado pelos dois ambientes (a gestão também exige o
// aceite): auth multi-guard, fora do gate lgpd.accepted (evita loop).
Route::middleware(['auth:web,gestao', 'verified'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('termo-lgpd', [LgpdTermController::class, 'show'])->name('termo-lgpd.show');
        Route::post('termo-lgpd', [LgpdTermController::class, 'accept'])->name('termo-lgpd.accept');
    });

// Guard web explícito: a sessão do console (guard gestao) não dá acesso
// ao portal — autenticações independentes por ambiente.
Route::middleware(['auth:web', 'verified'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
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
            Route::post('empresas/consultar-cnpj', CnpjLookupController::class)
                ->middleware('throttle:cnpj-lookup')
                ->name('empresas.consultar-cnpj');

            // Rotas com binding {company} DEPOIS das literais (03-05).
            Route::get('empresas/{company}', [CompanyController::class, 'show'])->name('empresas.show');
            Route::put('empresas/{company}', [CompanyController::class, 'update'])->name('empresas.update');
            Route::delete('empresas/{company}/vinculo', [CompanyLinkController::class, 'destroy'])->name('empresas.vinculo.destroy');

            // CNAEs da empresa (HU-025/HU-026) — escrita só via service (03-06).
            Route::put('empresas/{company}/cnae-principal', [CompanyCnaeController::class, 'updatePrimary'])->name('empresas.cnae-principal');
            Route::put('empresas/{company}/cnaes-secundarios', [CompanyCnaeController::class, 'updateSecondaries'])->name('empresas.cnaes-secundarios');

            // Busca da tabela oficial para os selects de CNAE (só ativos).
            Route::get('cnaes', CnaeSearchController::class)->name('cnaes.search');
        });
    });
