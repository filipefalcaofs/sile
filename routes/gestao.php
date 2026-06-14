<?php

use App\Http\Controllers\Gestao\AccessHistoryController;
use App\Http\Controllers\Gestao\CnaeController;
use App\Http\Controllers\Gestao\DashboardController;
use App\Http\Controllers\Gestao\EmailLogController;
use App\Http\Controllers\Gestao\GeocodeController;
use App\Http\Controllers\Gestao\LoginController;
use App\Http\Controllers\Gestao\LouosController;
use App\Http\Controllers\Gestao\ParameterController;
use App\Http\Controllers\Gestao\RiscoCondicionanteController;
use App\Http\Controllers\Gestao\RiscoController;
use App\Http\Controllers\Gestao\RoleController;
use App\Http\Controllers\Gestao\TerritoryController;
use App\Http\Controllers\Gestao\UserManagementController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Login interno da retaguarda (não divulgado no portal público), em guard
// próprio (gestao): a sessão é independente da sessão do portal do cidadão.
Route::middleware('gestao.guest')->prefix('gestao')->name('gestao.')->group(function () {
    Route::get('login', fn () => Inertia::render('auth/gestao-login'))->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth:gestao')
    ->post('gestao/logout', [LoginController::class, 'destroy'])
    ->name('gestao.logout');

// Sem middleware verified: e-mail verificado é pré-condição do PRÓPRIO login
// interno (LoginController) — o aviso/reenvio de verificação pertence ao
// fluxo do portal e exige o guard web.
Route::middleware(['auth:gestao', 'permission:acessar-gestao', 'lgpd.accepted'])
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

        Route::middleware('permission:manter-usuarios')->group(function () {
            Route::get('usuarios', [UserManagementController::class, 'index'])->name('usuarios.index');
            Route::put('usuarios/{user}/papel', [UserManagementController::class, 'updateRole'])->name('usuarios.papel.update');
            Route::put('usuarios/{user}/inativacao', [UserManagementController::class, 'toggleActivation'])->name('usuarios.inativacao.update');
        });

        Route::middleware('permission:manter-perfis')->group(function () {
            Route::get('perfis', [RoleController::class, 'index'])->name('perfis.index');
            Route::post('perfis', [RoleController::class, 'store'])->name('perfis.store');
            Route::put('perfis/{role}', [RoleController::class, 'update'])->name('perfis.update');
            Route::delete('perfis/{role}', [RoleController::class, 'destroy'])->name('perfis.destroy');
        });

        Route::middleware('permission:monitorar-emails')->group(function () {
            Route::get('emails', [EmailLogController::class, 'index'])->name('emails.index');
        });

        Route::middleware('permission:manter-parametros')->group(function () {
            Route::get('parametros', [ParameterController::class, 'index'])->name('parametros.index');
            Route::get('parametros/{parameter:key}/historico', [ParameterController::class, 'history'])->name('parametros.historico');
            Route::put('parametros/{parameter:key}', [ParameterController::class, 'update'])->name('parametros.update');
        });

        // Território (HU-029+): consulta territorial, geocodificação e validação
        // de localização atrás de permissão própria. O gate é este middleware
        // permission: (a permissão vive no guard web e resolve para o usuário do
        // guard gestao). A geocodificação tem throttle parametrizado próprio.
        Route::middleware('permission:consultar-territorio')->prefix('territorio')->name('territorio.')->group(function () {
            Route::get('/', [TerritoryController::class, 'index'])->name('index');
            Route::post('geocodificar', GeocodeController::class)->middleware('throttle:geocoding')->name('geocodificar');
            Route::post('identificar', [TerritoryController::class, 'identify'])->name('identificar');
            Route::post('validar-localizacao', [TerritoryController::class, 'validateLocation'])->name('validar-localizacao');
        });

        // Classificação de risco (HU-019/HU-020/HU-052/HU-053): a consulta da
        // tabela vigente (analista/gestor/admin) é separada da manutenção
        // versionada e do CRUD de condicionantes (admin). Gate cross-guard via
        // permission: (PADRÃO 04-03). Atualizar publica NOVA versão (4-olhos),
        // nunca edição destrutiva da vigente.
        Route::middleware('permission:consultar-risco')->prefix('risco')->name('risco.')->group(function () {
            Route::get('/', [RiscoController::class, 'index'])->name('index');
            Route::get('condicionantes', [RiscoCondicionanteController::class, 'index'])->name('condicionantes.index');
        });

        Route::middleware('permission:manter-risco')->prefix('risco')->name('risco.')->group(function () {
            Route::put('publicar', [RiscoController::class, 'publish'])->name('publicar');
            Route::post('condicionantes', [RiscoCondicionanteController::class, 'store'])->name('condicionantes.store');
            Route::put('condicionantes/{condicionante}', [RiscoCondicionanteController::class, 'update'])->name('condicionantes.update');
            Route::delete('condicionantes/{condicionante}', [RiscoCondicionanteController::class, 'destroy'])->name('condicionantes.destroy');
        });

        // Quadros da LOUOS (HU-015..018/HU-046): a consulta da versão vigente dos
        // 4 Quadros (analista/gestor/admin) é separada da publicação versionada
        // (admin). Gate cross-guard via permission: (PADRÃO 04-03/06). Publicar
        // gera NOVA versão por quatro olhos, nunca edição destrutiva da vigente.
        Route::middleware('permission:consultar-louos')->prefix('louos')->name('louos.')->group(function () {
            Route::get('/', [LouosController::class, 'index'])->name('index');
        });

        Route::middleware('permission:manter-louos')->prefix('louos')->name('louos.')->group(function () {
            Route::put('publicar', [LouosController::class, 'publish'])->name('publicar');
        });
    });
